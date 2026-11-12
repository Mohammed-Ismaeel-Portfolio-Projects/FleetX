<?php

namespace App\Actions\Tracking;

use App\Core\ApiResult;
use App\Models\TrackingLog;
use Illuminate\Support\Facades\DB;

class CreateTrackingLogAction
{
    public function __construct(
        private UpdateTrackingStateAction $updateTrackingStateAction
    ) {}

    public function execute(array $data): ApiResult
    {
        try {
            return DB::transaction(function () use ($data) {
                $trackingLog = TrackingLog::create($data);

                $stateResult = $this->updateTrackingStateAction->execute($trackingLog);
                if (! $stateResult->success) {
                    return ApiResult::error(
                        $stateResult->message ?: 'Failed to update tracking state',
                        $stateResult->statusCode ?: 500
                    );
                }

                return ApiResult::success($trackingLog->fresh(['shipment']), 'Tracking log created successfully');
            });
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to create tracking log: ' . $e->getMessage(), 500);
        }
    }
}
