<?php

namespace App\Actions\Assignment;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Support\EngineEnrollmentEligibility;
use App\Support\EngineEnrollmentStatus;
use Illuminate\Support\Facades\DB;

/**
 * Explicitly enroll an operational Assignment into the simulation Engine (C2.9-C2.8 Step 3).
 *
 * Does not advance Shipment status. Idempotent under row lock.
 */
class EnrollAssignmentInEngineAction
{
    public function execute(string|int $assignmentId): ApiResult
    {
        return DB::transaction(function () use ($assignmentId) {
            /** @var Assignment|null $assignment */
            $assignment = Assignment::query()
                ->whereKey($assignmentId)
                ->lockForUpdate()
                ->with(['shipment.status', 'vehicle', 'driver'])
                ->first();

            if ($assignment === null) {
                return ApiResult::notFound('Assignment not found');
            }

            $eligibility = EngineEnrollmentEligibility::assess($assignment);

            if ($eligibility['already_enrolled']) {
                return ApiResult::success(
                    $assignment->fresh(['shipment.status', 'vehicle', 'driver']),
                    'Assignment already enrolled in engine'
                );
            }

            if (! $eligibility['can_enroll']) {
                $errors = null;
                if ($eligibility['block_reason'] === 'Shipment must be Assigned before engine enrollment') {
                    $errors = ['status' => $assignment->shipment?->status?->name];
                }

                return ApiResult::error(
                    $eligibility['block_reason'] ?? 'Assignment is not eligible for engine enrollment',
                    422,
                    $errors
                );
            }

            $assignment->update([
                'engine_enrollment_status' => EngineEnrollmentStatus::ENROLLED,
                'engine_enrolled_at' => now(),
            ]);

            return ApiResult::success(
                $assignment->fresh(['shipment.status', 'vehicle', 'driver']),
                'Assignment enrolled in engine'
            );
        });
    }
}
