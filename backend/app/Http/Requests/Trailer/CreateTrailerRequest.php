<?php

namespace App\Http\Requests\Trailer;

use Illuminate\Foundation\Http\FormRequest;

class CreateTrailerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentYear = (int) date('Y') + 1;

        return [
            'trailer_code' => ['required', 'string', 'max:50', 'unique:trailers,trailer_code'],
            'fleet_number' => ['required', 'string', 'max:50', 'unique:trailers,fleet_number'],
            'plate_number' => ['required', 'string', 'max:50', 'unique:trailers,plate_number'],
            'trailer_type' => ['required', 'string', 'max:50'],
            'manufacturer' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'production_year' => ['nullable', 'integer', 'min:1980', 'max:' . $currentYear],
            'axle_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'payload_capacity_kg' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'length_m' => ['nullable', 'numeric', 'min:1', 'max:25'],
            'width_m' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'height_m' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'cargo_volume_m3' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'current_odometer' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'gps_device_identifier' => ['nullable', 'string', 'max:100'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'inspection_expiry_date' => ['nullable', 'date'],
            'registration_expiry_date' => ['nullable', 'date'],
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'trailer_code.unique' => 'A trailer with this code already exists.',
            'fleet_number.unique' => 'A trailer with this fleet number already exists.',
            'plate_number.unique' => 'A trailer with this plate number already exists.',
            'production_year.min' => 'Production year is too old for active freight fleet records.',
            'payload_capacity_kg.min' => 'Payload capacity must be greater than zero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'trailer_code' => $this->input('trailer_code') ? strtoupper(trim($this->input('trailer_code'))) : null,
            'fleet_number' => $this->input('fleet_number') ? strtoupper(trim($this->input('fleet_number'))) : null,
            'plate_number' => $this->input('plate_number') ? strtoupper(trim($this->input('plate_number'))) : null,
            'trailer_type' => $this->input('trailer_type') ? trim($this->input('trailer_type')) : null,
            'manufacturer' => $this->input('manufacturer') ? trim($this->input('manufacturer')) : null,
            'model' => $this->input('model') ? trim($this->input('model')) : null,
            'gps_device_identifier' => $this->input('gps_device_identifier') ? strtoupper(trim($this->input('gps_device_identifier'))) : null,
        ]);
    }
}
