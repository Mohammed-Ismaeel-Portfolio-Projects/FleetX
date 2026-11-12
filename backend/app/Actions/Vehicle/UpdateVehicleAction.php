<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class UpdateVehicleAction
{
    public function execute(Vehicle $vehicle, array $data): ApiResult
    {
        try {
            return DB::transaction(function () use ($vehicle, $data) {
                $this->validateBusinessRules($vehicle, $data);

                $updateData = $this->filterChangedFields($vehicle, $data);

                if (empty($updateData)) {
                    return ApiResult::success($vehicle, 'No changes detected');
                }

                $vehicle->update($this->prepareUpdateData($updateData));
                $vehicle->refresh();

                return ApiResult::success($vehicle, 'Vehicle updated successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to update vehicle: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function validateBusinessRules(Vehicle $vehicle, array $data): void
    {
        $payload = $data['payload_capacity_kg'] ?? $vehicle->payload_capacity_kg;
        $grossWeight = $data['gross_weight_kg'] ?? $vehicle->gross_weight_kg;

        if ($payload !== null && $grossWeight !== null && (int) $payload > (int) $grossWeight) {
            throw new \Exception('Payload capacity cannot exceed gross vehicle weight.');
        }
    }

    protected function filterChangedFields(Vehicle $vehicle, array $data): array
    {
        $changed = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($vehicle->$key != $value) {
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
