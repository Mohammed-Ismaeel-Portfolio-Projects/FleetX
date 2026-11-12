<?php

namespace App\Actions\Assignment;

use App\Core\ApiResult;
use App\Models\Assignment;
use Illuminate\Support\Facades\DB;

class RestoreAssignmentAction
{
    public function execute(string $assignmentId, int $userId): ApiResult
    {
        return DB::transaction(function () use ($assignmentId, $userId) {
            $assignment = Assignment::withTrashed()->find($assignmentId);

            if (!$assignment) {
                return ApiResult::notFound('Assignment not found');
            }

            if (!$assignment->trashed()) {
                return ApiResult::error('Assignment is not deleted', 400);
            }

            $assignment->restore();
            $assignment->update([
                'updated_by' => $userId,
            ]);

            return ApiResult::success($assignment->load(['shipment', 'driver', 'vehicle', 'trailer', 'status', 'assignedBy']), 'Assignment restored successfully');
        });
    }
}
