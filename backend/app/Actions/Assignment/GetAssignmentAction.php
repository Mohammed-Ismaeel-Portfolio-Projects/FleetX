<?php

namespace App\Actions\Assignment;

use App\Core\ApiResult;
use App\Models\Assignment;

class GetAssignmentAction
{
    public function execute(string $assignmentId): ApiResult
    {
        $assignment = Assignment::with(['shipment', 'driver', 'vehicle', 'trailer', 'status', 'assignedBy', 'creator', 'updater'])
            ->find($assignmentId);

        if (!$assignment) {
            return ApiResult::notFound('Assignment not found');
        }

        return ApiResult::success($assignment, 'Assignment retrieved successfully');
    }
}
