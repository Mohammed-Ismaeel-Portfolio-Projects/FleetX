<?php

namespace App\Actions\Issue;

use App\Core\ApiResult;
use App\Models\Issue;

class CreateIssueAction
{
    public function execute(array $data): ApiResult
    {
        try {
            $issue = Issue::create($data);

            $issue->load(['shipment', 'driver', 'status']);

            return ApiResult::success($issue, 'Issue created successfully', 201);
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to create issue: ' . $e->getMessage(),
                500
            );
        }
    }
}
