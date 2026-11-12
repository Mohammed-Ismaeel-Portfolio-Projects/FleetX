<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;

class ToggleDriverStatusAction
{
    public function execute(Driver $driver, bool $isActive): ApiResult
    {
        try {
            return DB::transaction(function () use ($driver, $isActive) {
                if ($driver->is_active === $isActive) {
                    return ApiResult::success(
                        [
                            'id' => $driver->id,
                            'is_active' => $driver->is_active,
                        ],
                        'Driver already has the requested status'
                    );
                }

                $updateData = [
                    'is_active' => $isActive,
                ];

                if (auth()->check()) {
                    $updateData['updated_by'] = auth()->id();
                }

                $driver->update($updateData);
                $driver->refresh();

                return ApiResult::success(
                    [
                        'id' => $driver->id,
                        'is_active' => $driver->is_active,
                    ],
                    $isActive ? 'Driver activated successfully' : 'Driver deactivated successfully'
                );
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to update driver status: ' . $e->getMessage(),
                500
            );
        }
    }
}
