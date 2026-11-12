<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class CreateVehicleAction
{
    public function execute(array $data): ApiResult
    {
        try {
            return DB::transaction(function () use ($data) {
                $this->validateBusinessRules($data);

                $vehicle = Vehicle::create($this->prepareVehicleData($data));

                return ApiResult::success($vehicle, 'Vehicle created successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to create vehicle: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function validateBusinessRules(array $data): void
    {
        if (
            isset($data['payload_capacity_kg'], $data['gross_weight_kg'])
            && (int) $data['payload_capacity_kg'] > (int) $data['gross_weight_kg']
        ) {
            throw new \Exception('Payload capacity cannot exceed gross vehicle weight.');
        }
    }

    protected function prepareVehicleData(array $data): array
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
