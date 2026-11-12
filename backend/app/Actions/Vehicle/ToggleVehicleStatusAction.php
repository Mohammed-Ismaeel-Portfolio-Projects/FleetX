<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class ToggleVehicleStatusAction
{
    public function execute(Vehicle $vehicle, bool $isActive): ApiResult
    {
        try {
            return DB::transaction(function () use ($vehicle, $isActive) {
                if ($vehicle->is_active === $isActive) {
                    return ApiResult::success(
                        [
                            'id' => $vehicle->id,
                            'is_active' => $vehicle->is_active,
                        ],
                        'Vehicle already has the requested status'
                    );
                }

                $updateData = [
                    'is_active' => $isActive,
                ];

                if (auth()->check()) {
                    $updateData['updated_by'] = auth()->id();
                }

                $vehicle->update($updateData);
                $vehicle->refresh();

                return ApiResult::success(
                    [
                        'id' => $vehicle->id,
                        'is_active' => $vehicle->is_active,
                    ],
                    $isActive ? 'Vehicle activated successfully' : 'Vehicle deactivated successfully'
                );
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to update vehicle status: ' . $e->getMessage(),
                500
            );
        }
    }
}
