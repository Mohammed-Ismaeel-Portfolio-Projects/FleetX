<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;

/**
 * Driver Operations KPIs — SSOT via GetDriverAvailabilityAction (Fleet parity).
 */
class GetDriverKPIsAction
{
    public function __construct(
        private readonly GetDriverAvailabilityAction $availabilityAction
    ) {
    }

    public function execute(): ApiResult
    {
        $result = $this->availabilityAction->execute();

        if (!$result->success) {
            return $result;
        }

        $data = $result->data;

        $kpis = [
            'total_drivers' => (int) ($data['total'] ?? 0),
            'available' => (int) ($data['available'] ?? 0),
            'busy' => (int) ($data['busy'] ?? 0),
            'offline' => (int) ($data['offline'] ?? 0),
            'on_leave' => (int) ($data['on_leave'] ?? 0),
            'suspended' => (int) ($data['suspended'] ?? 0),
            'available_percentage' => (float) ($data['available_percentage'] ?? 0),
            'busy_percentage' => (float) ($data['busy_percentage'] ?? 0),
            'offline_percentage' => (float) ($data['offline_percentage'] ?? 0),
            'on_leave_percentage' => (float) ($data['on_leave_percentage'] ?? 0),
            'suspended_percentage' => (float) ($data['suspended_percentage'] ?? 0),
        ];

        return ApiResult::success($kpis, 'Driver KPIs retrieved successfully');
    }
}
