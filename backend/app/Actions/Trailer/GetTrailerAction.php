<?php

namespace App\Actions\Trailer;

use App\Core\ApiResult;
use App\Exceptions\NotFoundException;
use App\Models\Trailer;

class GetTrailerAction
{
    public function execute(string $trailerId): ApiResult
    {
        try {
            $trailer = Trailer::find($trailerId);

            if (!$trailer) {
                throw new NotFoundException('Trailer not found');
            }

            return ApiResult::success($trailer, 'Trailer retrieved successfully');
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve trailer: ' . $e->getMessage(),
                500
            );
        }
    }
}
