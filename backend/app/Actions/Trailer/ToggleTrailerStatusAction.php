<?php

namespace App\Actions\Trailer;

use App\Core\ApiResult;
use App\Models\Trailer;
use Illuminate\Support\Facades\DB;

class ToggleTrailerStatusAction
{
    public function execute(Trailer $trailer, bool $isActive): ApiResult
    {
        try {
            return DB::transaction(function () use ($trailer, $isActive) {
                if ($trailer->is_active === $isActive) {
                    return ApiResult::success(
                        [
                            'id' => $trailer->id,
                            'is_active' => $trailer->is_active,
                        ],
                        'Trailer already has the requested status'
                    );
                }

                $updateData = [
                    'is_active' => $isActive,
                ];

                if (auth()->check()) {
                    $updateData['updated_by'] = auth()->id();
                }

                $trailer->update($updateData);
                $trailer->refresh();

                return ApiResult::success(
                    [
                        'id' => $trailer->id,
                        'is_active' => $trailer->is_active,
                    ],
                    $isActive ? 'Trailer activated successfully' : 'Trailer deactivated successfully'
                );
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to update trailer status: ' . $e->getMessage(),
                500
            );
        }
    }
}
