<?php

namespace App\Actions\Trailer;

use App\Core\ApiResult;
use App\Exceptions\NotFoundException;
use App\Models\Trailer;
use Illuminate\Support\Facades\DB;

class RestoreTrailerAction
{
    public function execute(string $trailerId): ApiResult
    {
        try {
            return DB::transaction(function () use ($trailerId) {
                $trailer = Trailer::withTrashed()->find($trailerId);

                if (!$trailer) {
                    throw new NotFoundException('Trailer not found');
                }

                if (!$trailer->trashed()) {
                    return ApiResult::success(null, 'Trailer is already active');
                }

                if (auth()->check()) {
                    $trailer->updated_by = auth()->id();
                    $trailer->save();
                }

                $trailer->restore();

                return ApiResult::success(null, 'Trailer restored successfully');
            });
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to restore trailer: ' . $e->getMessage(),
                500
            );
        }
    }
}
