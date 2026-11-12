<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Exceptions\NotFoundException;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;

class RestoreDriverAction
{
    public function execute(string $driverId): ApiResult
    {
        try {
            return DB::transaction(function () use ($driverId) {
                $driver = Driver::withTrashed()->find($driverId);

                if (!$driver) {
                    throw new NotFoundException('Driver not found');
                }

                if (!$driver->trashed()) {
                    return ApiResult::success(null, 'Driver is already active');
                }

                if (auth()->check()) {
                    $driver->updated_by = auth()->id();
                    $driver->save();
                }

                $driver->restore();

                return ApiResult::success(null, 'Driver restored successfully');
            });
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to restore driver: ' . $e->getMessage(),
                500
            );
        }
    }
}
