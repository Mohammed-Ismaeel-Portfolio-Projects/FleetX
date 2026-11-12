<?php

namespace App\Actions\Tracking;

use App\Core\ApiResult;
use App\Models\TrackingLog;

class GetTrackingLogAction
{
    public function execute(string $id): ApiResult
    {
        try {
            $trackingLog = TrackingLog::with(['shipment'])->findOrFail($id);

            return ApiResult::success($trackingLog, 'Tracking log retrieved successfully');
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to retrieve tracking log: ' . $e->getMessage(), 404);
        }
    }
}
