<?php

namespace App\Actions\Trailer;

use App\Core\ApiResult;
use App\Models\Trailer;
use Illuminate\Support\Facades\DB;

class CreateTrailerAction
{
    public function execute(array $data): ApiResult
    {
        try {
            return DB::transaction(function () use ($data) {
                $this->validateBusinessRules($data);

                $trailer = Trailer::create($this->prepareTrailerData($data));

                return ApiResult::success($trailer, 'Trailer created successfully');
            });
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to create trailer: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function validateBusinessRules(array $data): void
    {
        // Check for duplicate trailer code if business requires uniqueness
        if (isset($data['trailer_code'])) {
            $existingCode = Trailer::withTrashed()
                ->where('trailer_code', $data['trailer_code'])
                ->first();

            if ($existingCode) {
                throw new \Exception('A trailer with this code already exists.');
            }
        }

        // Additional business rules can be added here
        // - Payload capacity validation
        // - Dimension validation
        // - etc.
    }

    protected function prepareTrailerData(array $data): array
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
