<?php

namespace App\Actions\Issue;

use App\Core\ApiResult;
use App\Models\Issue;

class DeleteIssueAction
{
    public function execute(Issue $issue): ApiResult
    {
        try {
            $issue->delete();

            return ApiResult::success(null, 'Issue deleted successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to delete issue: ' . $e->getMessage(),
                500
            );
        }
    }
}
