<?php

namespace App\Actions\Assignment;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Trailer;
use App\Models\Status;
use App\Services\DriverEvaluation\DriverEvaluationLifecycleService;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use Illuminate\Support\Facades\DB;

class ChangeAssignmentStatusAction
{
    public function execute(Assignment $assignment, int $statusId, int $userId, ?string $endedAt = null): ApiResult
    {
        $result = DB::transaction(function () use ($assignment, $statusId, $userId, $endedAt) {
            $completedStatus = Status::where('name', 'Completed')->where('type', 'assignment')->first();
            $cancelledStatus = Status::where('name', 'Cancelled')->where('type', 'assignment')->first();
            $availableStatus = Status::where('name', 'Available')->first();

            // Update assignment status
            $assignment->update([
                'status_id' => $statusId,
                'ended_at' => $endedAt,
                'updated_by' => $userId,
            ]);

            // If assignment is being completed or cancelled, restore assets to Available
            if (($completedStatus && $statusId === $completedStatus->id) || ($cancelledStatus && $statusId === $cancelledStatus->id)) {
                $assignment->update([
                    'is_active' => false,
                ]);

                // Restore driver to Available
                if ($assignment->driver_id && $availableStatus) {
                    Driver::where('id', $assignment->driver_id)->update([
                        'status_id' => Status::where('name', 'Available')->where('type', 'driver')->first()?->id ?? $availableStatus->id,
                        'updated_by' => $userId,
                    ]);
                }

                // Restore vehicle to Available
                if ($assignment->vehicle_id && $availableStatus) {
                    Vehicle::where('id', $assignment->vehicle_id)->update([
                        'status_id' => Status::where('name', 'Available')->where('type', 'vehicle')->first()?->id ?? $availableStatus->id,
                        'updated_by' => $userId,
                    ]);
                }

                // Restore trailer to Available
                if ($assignment->trailer_id && $availableStatus) {
                    Trailer::where('id', $assignment->trailer_id)->update([
                        'status_id' => Status::where('name', 'Available')->where('type', 'trailer')->first()?->id ?? $availableStatus->id,
                        'updated_by' => $userId,
                    ]);
                }
            }

            return ApiResult::success($assignment->load(['shipment', 'driver', 'vehicle', 'trailer', 'status', 'assignedBy']), 'Assignment status changed successfully');
        });

        if ($result->success && $result->data instanceof Assignment) {
            /** @var Assignment $fresh */
            $fresh = $result->data;
            $cancelledStatus = Status::where('name', 'Cancelled')->where('type', 'assignment')->first();
            $completedStatus = Status::where('name', 'Completed')->where('type', 'assignment')->first();

            if ($cancelledStatus && (int) $fresh->status_id === (int) $cancelledStatus->id && $fresh->driver_id) {
                DriverNotificationWriter::forAssignment(
                    $fresh,
                    DriverNotificationCode::ASSIGNMENT_CANCELLED,
                    'Assignment cancelled',
                    'Your assignment was cancelled. Return to Work for next steps.',
                    'critical',
                    DriverNotificationAction::KIND_WORK
                );
            }

            // DRIVER-EVAL-07: ops completion also finalizes LIVE evaluation (idempotent).
            if ($completedStatus && (int) $fresh->status_id === (int) $completedStatus->id) {
                app(DriverEvaluationLifecycleService::class)->finalizeForCompletedAssignmentSafely($fresh);
            }
        }

        return $result;
    }
}
