<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;

class UpdateDriverAction
{
    public function execute(Driver $driver, array $data): ApiResult
    {
        try {
            return DB::transaction(function () use ($driver, $data) {
                $this->validateBusinessRules($driver, $data);

                $updateData = $this->filterChangedFields($driver, $data);

                if (empty($updateData)) {
                    return ApiResult::success($driver, 'No changes detected');
                }

                $driver->update($this->prepareUpdateData($updateData));
                $driver->refresh();

                return ApiResult::success($driver, 'Driver updated successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to update driver: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function validateBusinessRules(Driver $driver, array $data): void
    {
        // Additional business rules can be added here
        // - License expiry validation
        // - Medical certificate validation
        // - Age validation
    }

    protected function filterChangedFields(Driver $driver, array $data): array
    {
        $changed = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($driver->$key != $value) {
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
