<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class DeleteVehicleAction
{
    public function execute(Vehicle $vehicle): ApiResult
    {
        try {
            return DB::transaction(function () use ($vehicle) {
                if ($vehicle->trashed()) {
                    return ApiResult::success(null, 'Vehicle already deleted');
                }

                if (auth()->check()) {
                    $vehicle->updated_by = auth()->id();
                    $vehicle->save();
                }

                $vehicle->delete();

                return ApiResult::success(null, 'Vehicle deleted successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to delete vehicle: ' . $e->getMessage(),
                500
            );
        }
    }
}
