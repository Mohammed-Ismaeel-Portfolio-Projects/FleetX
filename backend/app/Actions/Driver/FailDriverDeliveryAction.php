<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Issue;
use App\Models\Status;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use App\Support\DriverIssueType;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use App\Support\ShipmentStatus;
use Illuminate\Support\Facades\DB;

/**
 * Controlled failed-delivery path (D7).
 * Creates Open issue + delivery_failed phase. Never marks Delivered / never completes.
 */
class FailDriverDeliveryAction
{
    public function execute(User $user, int|string $assignmentId, array $payload): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $type = (string) ($payload['type'] ?? '');
        if (! DriverIssueType::isValid($type)) {
            return ApiResult::error('Invalid issue type.', 422, [
                'code' => 'INVALID_ISSUE_TYPE',
            ]);
        }

        $description = isset($payload['description']) ? trim((string) $payload['description']) : '';
        if (DriverIssueType::requiresDescription($type) && $description === '') {
            return ApiResult::error('Description is required for Other.', 422, [
                'code' => 'DESCRIPTION_REQUIRED',
            ]);
        }

        if ($description === '') {
            $description = $type;
        }

        $result = DB::transaction(function () use ($user, $driver, $assignmentId, $type, $description) {
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

            if (! $assignment->is_active || $statusName !== DriverAssignmentTransitionMap::IN_OPERATION) {
                return ApiResult::error('Assignment is not in operation.', 409, [
                    'code' => 'ASSIGNMENT_NOT_IN_OPERATION',
                ]);
            }

            $phase = $assignment->execution_phase;

            if ($phase === DriverExecutionPhase::DELIVERY_FAILED) {
                $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

                return ApiResult::success([
                    'assignment' => $assignment,
                    'failed' => true,
                ], 'Delivery failure already recorded');
            }

            if (in_array($phase, [
                DriverExecutionPhase::DELIVERY_VERIFIED,
                DriverExecutionPhase::POD_SUBMITTED,
            ], true)) {
                return ApiResult::error(
                    'Cannot mark failed delivery after successful delivery verification.',
                    409,
                    ['code' => 'ALREADY_VERIFIED']
                );
            }

            $allowedPhases = [
                DriverExecutionPhase::DELIVERY_EN_ROUTE,
                DriverExecutionPhase::DELIVERY_ARRIVED,
                DriverExecutionPhase::DELIVERY_UNLOADING,
                DriverExecutionPhase::DELIVERY_VERIFYING,
            ];

            if (! in_array($phase, $allowedPhases, true)) {
                return ApiResult::error('Failed delivery is only allowed during the delivery stage.', 409, [
                    'code' => 'INVALID_PHASE',
                ]);
            }

            $shipmentStatus = $assignment->shipment?->status?->name;
            if ($shipmentStatus === ShipmentStatus::DELIVERED || $shipmentStatus === ShipmentStatus::COMPLETED) {
                return ApiResult::error('Shipment is already delivered.', 409, [
                    'code' => 'ALREADY_DELIVERED',
                ]);
            }

            if ($assignment->shipment_id === null) {
                return ApiResult::error('Assignment has no shipment.', 422);
            }

            $openStatus = Status::query()
                ->where('name', 'Open')
                ->where('type', 'issue')
                ->first();

            if ($openStatus === null) {
                return ApiResult::error('Open issue status is not configured.', 500);
            }

            Issue::query()->create([
                'shipment_id' => $assignment->shipment_id,
                'driver_id' => $driver->id,
                'type' => $type,
                'description' => $description,
                'status_id' => $openStatus->id,
            ]);

            $assignment->update([
                'execution_phase' => DriverExecutionPhase::DELIVERY_FAILED,
                'updated_by' => $user->id,
            ]);

            $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

            // Explicit: do not transition shipment to Delivered; do not complete assignment.
            return ApiResult::success([
                'assignment' => $assignment,
                'failed' => true,
            ], 'Delivery failure recorded');
        });

        if ($result->success && is_array($result->data) && isset($result->data['assignment'])) {
            /** @var Assignment $assignment */
            $assignment = $result->data['assignment'];
            DriverNotificationWriter::forAssignment(
                $assignment,
                DriverNotificationCode::DELIVERY_FAILED,
                'Delivery failure recorded',
                'Delivery could not be completed. Wait for dispatcher guidance.',
                'warning',
                DriverNotificationAction::KIND_ACTIVE_TRIP
            );
        }

        return $result;
    }
}
