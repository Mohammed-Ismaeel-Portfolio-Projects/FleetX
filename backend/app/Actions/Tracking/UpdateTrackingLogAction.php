<?php

namespace App\Actions\Tracking;

use App\Core\ApiResult;
use App\Models\TrackingLog;
use Illuminate\Support\Facades\DB;

class UpdateTrackingLogAction
{
    public function execute(TrackingLog $trackingLog, array $data): ApiResult
    {
        try {
            return DB::transaction(function () use ($trackingLog, $data) {
                $trackingLog->update($data);

                return ApiResult::success($trackingLog->fresh(['shipment']), 'Tracking log updated successfully');
            });
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to update tracking log: ' . $e->getMessage(), 500);
        }
    }
}
