<?php

namespace App\Actions\Assignment;

use App\Core\ApiResult;
use App\Models\Assignment;
use Illuminate\Support\Facades\DB;

class DeleteAssignmentAction
{
    public function execute(Assignment $assignment, int $userId): ApiResult
    {
        return DB::transaction(function () use ($assignment, $userId) {
            $assignment->update([
                'updated_by' => $userId,
            ]);

            $assignment->delete();

            return ApiResult::success(null, 'Assignment deleted successfully');
        });
    }
}
