<?php

namespace App\Support;

use App\Models\Assignment;

/**
 * Shared Engine enrollment eligibility assessment (C2.9-C2.8 Step 3 / Step 7).
 *
 * Single source of truth for readiness checks used by enrollment action and Dispatcher read model.
 * Does not mutate Assignment or Shipment.
 */
final class EngineEnrollmentEligibility
{
    /**
     * @return array{
     *     can_enroll: bool,
     *     already_enrolled: bool,
     *     enrollment_closed: bool,
     *     block_reason: string|null
     * }
     */
    public static function assess(?Assignment $assignment): array
    {
        if ($assignment === null) {
            return [
                'can_enroll' => false,
                'already_enrolled' => false,
                'enrollment_closed' => false,
                'block_reason' => 'Waiting for Assignment — Engine simulation unavailable',
            ];
        }

        if (EngineEnrollmentStatus::isSimulationEligible($assignment->engine_enrollment_status)) {
            return [
                'can_enroll' => false,
                'already_enrolled' => true,
                'enrollment_closed' => false,
                'block_reason' => null,
            ];
        }

        if (in_array($assignment->engine_enrollment_status, [
            EngineEnrollmentStatus::COMPLETED,
            EngineEnrollmentStatus::CANCELLED,
        ], true)) {
            return [
                'can_enroll' => false,
                'already_enrolled' => false,
                'enrollment_closed' => true,
                'block_reason' => 'Assignment engine enrollment is closed and cannot be re-enrolled',
            ];
        }

        if (! $assignment->is_active) {
            return [
                'can_enroll' => false,
                'already_enrolled' => false,
                'enrollment_closed' => false,
                'block_reason' => 'Assignment must be active to enroll in engine',
            ];
        }

        if ($assignment->shipment_id === null || $assignment->shipment === null) {
            return [
                'can_enroll' => false,
                'already_enrolled' => false,
                'enrollment_closed' => false,
                'block_reason' => 'Assignment must have a shipment to enroll in engine',
            ];
        }

        if ($assignment->vehicle_id === null || $assignment->vehicle === null) {
            return [
                'can_enroll' => false,
                'already_enrolled' => false,
                'enrollment_closed' => false,
                'block_reason' => 'Assignment must have a vehicle to enroll in engine',
            ];
        }

        if ($assignment->driver_id === null || $assignment->driver === null) {
            return [
                'can_enroll' => false,
                'already_enrolled' => false,
                'enrollment_closed' => false,
                'block_reason' => 'Assignment must have a driver to enroll in engine',
            ];
        }

        $shipment = $assignment->shipment;
        if ($shipment->status === null || $shipment->status->type !== ShipmentStatus::TYPE) {
            return [
                'can_enroll' => false,
                'already_enrolled' => false,
                'enrollment_closed' => false,
                'block_reason' => 'Shipment status is not a shipment lifecycle status',
            ];
        }

        if ($shipment->status->name !== ShipmentStatus::ASSIGNED) {
            return [
                'can_enroll' => false,
                'already_enrolled' => false,
                'enrollment_closed' => false,
                'block_reason' => 'Shipment must be Assigned before engine enrollment',
            ];
        }

        return [
            'can_enroll' => true,
            'already_enrolled' => false,
            'enrollment_closed' => false,
            'block_reason' => null,
        ];
    }
}
