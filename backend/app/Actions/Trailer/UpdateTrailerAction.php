<?php

namespace App\Actions\Trailer;

use App\Core\ApiResult;
use App\Models\Trailer;
use Illuminate\Support\Facades\DB;

class UpdateTrailerAction
{
    public function execute(Trailer $trailer, array $data): ApiResult
    {
        try {
            return DB::transaction(function () use ($trailer, $data) {
                $this->validateBusinessRules($trailer, $data);

                $updateData = $this->filterChangedFields($trailer, $data);

                if (empty($updateData)) {
                    return ApiResult::success($trailer, 'No changes detected');
                }

                $trailer->update($this->prepareUpdateData($updateData));
                $trailer->refresh();

                return ApiResult::success($trailer, 'Trailer updated successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to update trailer: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function validateBusinessRules(Trailer $trailer, array $data): void
    {
        // Additional business rules can be added here
        // - Payload capacity validation
        // - Dimension validation
        // - etc.
    }

    protected function filterChangedFields(Trailer $trailer, array $data): array
    {
        $changed = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($trailer->$key != $value) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    protected function prepareUpdateData(array $data): array
    {
        unset($data['id'], $data['created_by']);

        if (auth()->check()) {
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}
