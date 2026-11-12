<?php

namespace App\Actions\Tracking;

use App\Core\ApiResult;
use App\Models\TrackingLog;
use Illuminate\Support\Facades\DB;

class DeleteTrackingLogAction
{
    public function execute(TrackingLog $trackingLog): ApiResult
    {
        try {
            return DB::transaction(function () use ($trackingLog) {
                $trackingLog->delete();

                return ApiResult::success(null, 'Tracking log deleted successfully');
            });
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to delete tracking log: ' . $e->getMessage(), 500);
        }
    }
}
