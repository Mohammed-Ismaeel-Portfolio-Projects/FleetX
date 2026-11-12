<?php

namespace App\Actions\Driver;

use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Core\ApiResult;
use App\Enums\FinalPriceCalculationTrigger;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidShipmentTransitionException;
use App\Models\Assignment;
use App\Models\DeliveryProof;
use App\Models\Driver;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use App\Support\ShipmentStatus;
use App\Services\DriverEvaluation\DriverEvaluationLifecycleService;
use App\Services\Pricing\ShipmentFinalPricingService;
use Illuminate\Support\Facades\DB;

/**
 * Driver assignment completion after POD (D7).
 * Mirrors Ops completion side effects without exposing Ops mutation endpoints.
 * DRIVER-EVAL-07: finalizes LIVE evaluation after operational success (non-blocking).
 */
class CompleteDriverAssignmentAction
{
    public function __construct(
        private readonly TransitionShipmentStatusAction $transitionShipment,
        private readonly ShipmentFinalPricingService $finalPricingService,
    ) {}

    public function execute(User $user, int|string $assignmentId): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $notifyShipment = null;

        try {
            $result = DB::transaction(function () use ($user, $driver, $assignmentId, &$notifyShipment) {
                /** @var Assignment|null $assignment */
                $assignment = Assignment::query()
                    ->forDriver($driver->id)
                    ->lockForUpdate()
                    ->find($assignmentId);

                if ($assignment === null) {
                    return ApiResult::notFound('Assignment not found');
                }

                $assignment->loadMissing(['status', 'shipment.status']);
                $statusName = $assignment->status?->name;

                // Idempotent: already Completed
                if ($statusName === DriverAssignmentTransitionMap::COMPLETED && ! $assignment->is_active) {
                    $assignment->load(['status', 'shipment.status', 'shipment.deliveryProof', 'vehicle', 'trailer']);

                    return ApiResult::success([
                        'assignment' => $assignment,
                        'completed' => true,
                        'completed_at' => $assignment->ended_at?->format('Y-m-d H:i:s')
                            ?? $assignment->updated_at?->format('Y-m-d H:i:s'),
                    ], 'Assignment already completed');
                }

                if (! $assignment->is_active || $statusName !== DriverAssignmentTransitionMap::IN_OPERATION) {
                    return ApiResult::error('Assignment is not in operation.', 409, [
                        'code' => 'ASSIGNMENT_NOT_IN_OPERATION',
                    ]);
                }

                if (! DriverExecutionPhase::isCompleteEligiblePhase($assignment->execution_phase)) {
                    return ApiResult::error('Proof of delivery is required before completion.', 409, [
                        'code' => 'POD_REQUIRED',
                    ]);
                }

                $shipment = $assignment->shipment;
                if ($shipment === null) {
                    return ApiResult::error('Assignment has no shipment.', 422);
                }

                $proofExists = DeliveryProof::query()
                    ->where('shipment_id', $shipment->id)
                    ->exists();

                if (! $proofExists) {
                    return ApiResult::error('Proof of delivery is required before completion.', 409, [
                        'code' => 'POD_REQUIRED',
                    ]);
                }

                $shipmentStatus = $shipment->status?->name;
                if ($shipmentStatus === ShipmentStatus::DELIVERED) {
                    $shipment = $this->transitionShipment->execute(
                        $shipment,
                        ShipmentStatus::COMPLETED,
                        $user->id,
                        null,
                        null,
                        false
                    );
                    $notifyShipment = $shipment;
                } elseif ($shipmentStatus !== ShipmentStatus::COMPLETED) {
                    return ApiResult::error('Shipment is not eligible for completion.', 409, [
                        'code' => 'SHIPMENT_NOT_ELIGIBLE',
                    ]);
                }

                $completedStatus = Status::query()
                    ->where('name', DriverAssignmentTransitionMap::COMPLETED)
                    ->where('type', 'assignment')
                    ->first();

                if ($completedStatus === null) {
                    return ApiResult::error('Completed assignment status is not configured.', 500);
                }

                $endedAt = now();
                $assignment->update([
                    'status_id' => $completedStatus->id,
                    'is_active' => false,
                    'ended_at' => $endedAt,
                    'updated_by' => $user->id,
                ]);

                $this->restoreAssetsToAvailable($assignment, $user->id);

                $assignment->load(['status', 'shipment.status', 'shipment.deliveryProof', 'vehicle', 'trailer']);

                return ApiResult::success([
                    'assignment' => $assignment,
                    'completed' => true,
                    'completed_at' => $endedAt->format('Y-m-d H:i:s'),
                ], 'Assignment completed successfully');
            });
        } catch (InvalidShipmentTransitionException $e) {
            return ApiResult::error($e->getMessage(), 409, [
                'code' => 'SHIPMENT_TRANSITION_INVALID',
            ]);
        } catch (DomainException $e) {
            return ApiResult::error($e->getMessage(), $e->getCode() >= 400 ? (int) $e->getCode() : 422);
        }

        if ($result->success && $notifyShipment !== null) {
            $this->transitionShipment->notifyClientOfSuccessfulTransitionPublic(
                $notifyShipment,
                ShipmentStatus::COMPLETED
            );

            $this->finalPricingService->tryFinalizeOnCompletion(
                $notifyShipment->fresh(['status']),
                FinalPriceCalculationTrigger::COMPLETION_HOOK,
            );
        }

        if ($result->success && is_array($result->data) && isset($result->data['assignment'])) {
            /** @var Assignment $assignment */
            $assignment = $result->data['assignment'];
            DriverNotificationWriter::forAssignment(
                $assignment,
                DriverNotificationCode::DELIVERY_COMPLETED,
                'Delivery completed',
                'Trip completed successfully. You are available for the next assignment.',
                'info',
                DriverNotificationAction::KIND_WORK
            );

            // Outside completion transaction — evaluation failure must not corrupt completion.
            app(DriverEvaluationLifecycleService::class)->finalizeForCompletedAssignmentSafely($assignment);
        }

        return $result;
    }

    private function restoreAssetsToAvailable(Assignment $assignment, int $userId): void
    {
        $driverAvailable = Status::query()
            ->where('name', 'Available')
            ->where('type', 'driver')
            ->first();
        $vehicleAvailable = Status::query()
            ->where('name', 'Available')
            ->where('type', 'vehicle')
            ->first()
            ?? Status::query()->where('name', 'Available')->where('type', 'truck')->first();
        $trailerAvailable = Status::query()
            ->where('name', 'Available')
            ->where('type', 'trailer')
            ->first();

        if ($assignment->driver_id && $driverAvailable) {
            Driver::where('id', $assignment->driver_id)->update([
                'status_id' => $driverAvailable->id,
                'updated_by' => $userId,
            ]);
        }

        if ($assignment->vehicle_id && $vehicleAvailable) {
            Vehicle::where('id', $assignment->vehicle_id)->update([
                'status_id' => $vehicleAvailable->id,
                'updated_by' => $userId,
            ]);
        }

        if ($assignment->trailer_id && $trailerAvailable) {
            Trailer::where('id', $assignment->trailer_id)->update([
                'status_id' => $trailerAvailable->id,
                'updated_by' => $userId,
            ]);
        }
    }
}
