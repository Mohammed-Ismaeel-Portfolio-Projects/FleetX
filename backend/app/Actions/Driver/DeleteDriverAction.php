<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;

class DeleteDriverAction
{
    public function execute(Driver $driver): ApiResult
    {
        try {
            return DB::transaction(function () use ($driver) {
                if ($driver->trashed()) {
                    return ApiResult::success(null, 'Driver already deleted');
                }

                if (auth()->check()) {
                    $driver->updated_by = auth()->id();
                    $driver->save();
                }

                $driver->delete();

                return ApiResult::success(null, 'Driver deleted successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to delete driver: ' . $e->getMessage(),
                500
            );
        }
    }
}
