<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class CreateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentYear = (int) date('Y') + 1;

        return [
            'operational_code' => ['required', 'string', 'max:50', 'unique:vehicles,operational_code'],
            'fleet_number' => ['required', 'string', 'max:50', 'unique:vehicles,fleet_number'],
            'plate_number' => ['required', 'string', 'max:50', 'unique:vehicles,plate_number'],
            'vin' => ['nullable', 'string', 'max:50', 'unique:vehicles,vin'],
            'manufacturer' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'production_year' => ['nullable', 'integer', 'min:1980', 'max:' . $currentYear],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'fuel_type_id' => ['required', 'integer', 'exists:fuel_types,id'],
            'transmission' => ['nullable', 'string', 'max:30', 'in:manual,automatic,automated_manual'],
            'axle_count' => ['nullable', 'integer', 'min:2', 'max:12'],
            'payload_capacity_kg' => ['nullable', 'integer', 'min:1', 'max:250000'],
            'gross_weight_kg' => ['nullable', 'integer', 'min:1', 'max:300000'],
            'fuel_tank_capacity_l' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'current_odometer' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
            'is_active' => ['nullable', 'boolean'],
            'gps_device_identifier' => ['nullable', 'string', 'max:100'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'registration_expiry_date' => ['nullable', 'date'],
            'inspection_expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'fleet_number.unique' => 'A vehicle with this fleet number already exists.',
            'plate_number.unique' => 'A vehicle with this plate number already exists.',
            'vin.unique' => 'A vehicle with this VIN already exists.',
            'operational_code.unique' => 'A vehicle with this operational code already exists.',
            'production_year.min' => 'Production year is too old for active freight fleet records.',
            'payload_capacity_kg.min' => 'Payload capacity must be greater than zero.',
            'gross_weight_kg.min' => 'Gross weight must be greater than zero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'operational_code' => $this->input('operational_code') ? strtoupper(trim($this->input('operational_code'))) : null,
            'fleet_number' => $this->input('fleet_number') ? strtoupper(trim($this->input('fleet_number'))) : null,
            'plate_number' => $this->input('plate_number') ? strtoupper(trim($this->input('plate_number'))) : null,
            'vin' => $this->input('vin') ? strtoupper(trim($this->input('vin'))) : null,
            'manufacturer' => $this->input('manufacturer') ? trim($this->input('manufacturer')) : null,
            'model' => $this->input('model') ? trim($this->input('model')) : null,
            'transmission' => $this->input('transmission') ? strtolower(trim($this->input('transmission'))) : null,
            'gps_device_identifier' => $this->input('gps_device_identifier') ? strtoupper(trim($this->input('gps_device_identifier'))) : null,
        ]);
    }
}
