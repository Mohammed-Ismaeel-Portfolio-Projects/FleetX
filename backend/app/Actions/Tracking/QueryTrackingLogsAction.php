<?php

namespace App\Actions\Tracking;

use App\Core\ApiResult;
use App\Models\TrackingLog;
use Illuminate\Database\Eloquent\Builder;

class QueryTrackingLogsAction
{
    public function execute(array $data = []): ApiResult
    {
        try {
            $query = TrackingLog::query()->with(['shipment']);

            if (!empty($data['shipment_id'])) {
                $query->where('shipment_id', $data['shipment_id']);
            }

            if (!empty($data['from'])) {
                $query->where('recorded_at', '>=', $data['from']);
            }

            if (!empty($data['to'])) {
                $query->where('recorded_at', '<=', $data['to']);
            }

            $perPage = !empty($data['per_page']) ? (int) $data['per_page'] : 15;
            return ApiResult::success($query->orderByDesc('recorded_at')->paginate($perPage));
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to query tracking logs: ' . $e->getMessage(), 500);
        }
    }
}
