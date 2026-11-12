<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Company Resource
 * 
 * Transforms Company model for API responses.
 * Exposes only public fields, hiding internal implementation details.
 */
class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'legal_name' => $this->legal_name,
            'company_code' => $this->company_code,
            'tax_number' => $this->tax_number,
            'vat_number' => $this->vat_number,
            'business_registration_number' => $this->business_registration_number,
            'industry_id' => $this->industry_id,
            'company_size_id' => $this->company_size_id,
            'primary_email' => $this->primary_email,
            'primary_phone' => $this->primary_phone,
            'website' => $this->website,
            'country' => $this->country,
            'state_province' => $this->state_province,
            'district' => $this->district,
            'city' => $this->city,
            'building_number' => $this->building_number,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'currency_code' => $this->currency_code,
            'customer_type' => $this->customer_type?->value ?? $this->customer_type,
            'company_type' => $this->company_type?->value ?? $this->company_type,
            'language_code' => $this->language_code,
            'status_id' => $this->status_id,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'logo_path' => $this->logo_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
