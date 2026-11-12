<?php

namespace App\Actions\Issue;

use App\Core\ApiResult;
use App\Models\Issue;

class GetIssueAction
{
    public function execute(int $issueId): ApiResult
    {
        try {
            $issue = Issue::with(['shipment', 'driver', 'status'])->findOrFail($issueId);

            return ApiResult::success($issue, 'Issue retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve issue: ' . $e->getMessage(),
                404
            );
        }
    }
}
