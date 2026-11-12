<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;

class CreateDriverAction
{
    public function execute(array $data): ApiResult
    {
        try {
            return DB::transaction(function () use ($data) {
                $this->validateBusinessRules($data);

                $driver = Driver::create($this->prepareDriverData($data));

                return ApiResult::success($driver, 'Driver created successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to create driver: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function validateBusinessRules(array $data): void
    {
        // Check for duplicate employee number if business requires uniqueness
        if (isset($data['employee_number'])) {
            $existingEmployeeNumber = Driver::withTrashed()
                ->where('employee_number', $data['employee_number'])
                ->first();

            if ($existingEmployeeNumber) {
                throw new \Exception('A driver with this employee number already exists.');
            }
        }

        // Additional business rules can be added here
        // - License expiry validation
        // - Medical certificate validation
        // - Age validation (minimum age for drivers)
    }

    protected function prepareDriverData(array $data): array
    {
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        if (auth()->check()) {
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}
