<?php

namespace App\Actions\Issue;

use App\Core\ApiResult;
use App\Models\Issue;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;

class UpdateIssueAction
{
    public function execute(Issue $issue, array $data): ApiResult
    {
        try {
            $issue->update($data);

            $issue->load(['shipment', 'driver', 'status']);

            if ($issue->driver_id) {
                DriverNotificationWriter::notify(
                    (int) $issue->driver_id,
                    DriverNotificationCode::ISSUE_UPDATED,
                    'Issue update',
                    'An operational issue on your trip was updated by Ops.',
                    'info',
                    null,
                    DriverNotificationAction::KIND_WORK,
                    null,
                    DriverNotificationCode::ISSUE_UPDATED.':'.$issue->id.':'.$issue->updated_at?->timestamp
                );
            }

            return ApiResult::success($issue, 'Issue updated successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to update issue: '.$e->getMessage(),
                500
            );
        }
    }
}
