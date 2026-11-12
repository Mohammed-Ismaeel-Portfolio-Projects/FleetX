<?php

namespace App\Actions\Trailer;

use App\Core\ApiResult;
use App\Models\Trailer;
use Illuminate\Support\Facades\DB;

class DeleteTrailerAction
{
    public function execute(Trailer $trailer): ApiResult
    {
        try {
            return DB::transaction(function () use ($trailer) {
                if ($trailer->trashed()) {
                    return ApiResult::success(null, 'Trailer already deleted');
                }

                if (auth()->check()) {
                    $trailer->updated_by = auth()->id();
                    $trailer->save();
                }

                $trailer->delete();

                return ApiResult::success(null, 'Trailer deleted successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to delete trailer: ' . $e->getMessage(),
                500
            );
        }
    }
}
