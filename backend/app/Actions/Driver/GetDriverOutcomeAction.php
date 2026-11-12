<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\DeliveryProof;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use App\Support\ShipmentStatus;

/**
 * Driver-safe outcome projection (D7).
 */
class GetDriverOutcomeAction
{
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

        /** @var Assignment|null $assignment */
        $assignment = Assignment::query()
            ->forDriver($driver->id)
            ->with(['status', 'shipment.status', 'shipment.deliveryProof', 'vehicle', 'trailer'])
            ->find($assignmentId);

        if ($assignment === null) {
            return ApiResult::notFound('Assignment not found');
        }

        $phase = $assignment->execution_phase;
        $shipmentStatus = $assignment->shipment?->status?->name;
        $proof = $assignment->shipment?->deliveryProof
            ?? DeliveryProof::query()->where('shipment_id', $assignment->shipment_id)->first();

        $podSubmitted = $proof !== null;
        $deliveryVerified = DriverExecutionPhase::isPodEligible($phase)
            || $shipmentStatus === ShipmentStatus::DELIVERED
            || $shipmentStatus === ShipmentStatus::COMPLETED;

        $completeEligible = $assignment->is_active
            && $assignment->status?->name === DriverAssignmentTransitionMap::IN_OPERATION
            && DriverExecutionPhase::isCompleteEligiblePhase($phase)
            && $podSubmitted
            && in_array($shipmentStatus, [ShipmentStatus::DELIVERED, ShipmentStatus::COMPLETED], true);

        $failEligible = $assignment->is_active
            && $assignment->status?->name === DriverAssignmentTransitionMap::IN_OPERATION
            && in_array($phase, [
                DriverExecutionPhase::DELIVERY_EN_ROUTE,
                DriverExecutionPhase::DELIVERY_ARRIVED,
                DriverExecutionPhase::DELIVERY_UNLOADING,
                DriverExecutionPhase::DELIVERY_VERIFYING,
            ], true)
            && $shipmentStatus === ShipmentStatus::IN_TRANSIT;

        return ApiResult::success([
            'assignment' => $assignment,
            'outcome' => [
                'delivery_verified' => $deliveryVerified,
                'pod_submitted' => $podSubmitted,
                'pod_confirmed_at' => $proof?->confirmed_at?->format('Y-m-d H:i:s'),
                'complete_eligible' => $completeEligible,
                'fail_eligible' => $failEligible,
                'shipment_status' => $shipmentStatus,
                'execution_phase' => $phase,
            ],
        ], 'Outcome loaded');
    }
}
