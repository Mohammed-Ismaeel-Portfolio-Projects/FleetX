<?php

namespace App\Http\Requests\Admin;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\TrailerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminCalculatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('pricing.calculate')) {
            return false;
        }

        if ($this->hasOverrideFields() && ! $user->can('pricing.admin.calculate')) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->prohibitedFieldRules(),
            $this->calculationFieldRules(),
            $this->overrideFieldRules(),
        );
    }

    public function messages(): array
    {
        return [
            'tax_rate.prohibited' => 'Tax rate is server-resolved and cannot be supplied.',
            'discount.prohibited' => 'Discount is server-resolved and cannot be supplied.',
            'discount_amount.prohibited' => 'Discount amount is server-resolved and cannot be supplied.',
            'minimum_fare.prohibited' => 'Minimum fare is server-resolved and cannot be supplied.',
            'priority_multiplier.prohibited' => 'Priority multiplier is server-resolved and cannot be supplied.',
            'service_price.prohibited' => 'Service prices are server-resolved and cannot be supplied.',
            'service_prices.prohibited' => 'Service prices are server-resolved and cannot be supplied.',
            'base_rate.prohibited' => 'Base rate is server-resolved and cannot be supplied.',
            'distance_tier_rate.prohibited' => 'Distance tier rate is server-resolved and cannot be supplied.',
            'final_total.prohibited' => 'Final total is server-calculated and cannot be supplied.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            if ($user !== null && $user->company_id === null
                && ! $this->filled('customer_company_id')
                && ! $this->hasCustomerTypeOverride()) {
                $validator->errors()->add(
                    'customer_company_id',
                    'The customer company id field is required when the authenticated user has no company context.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['border_crossing', 'hazmat', 'insurance_required'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }

    public function hasOverrideFields(): bool
    {
        return $this->has('customer_type')
            || $this->has('distance_km')
            || $this->has('pricing_policy_id');
    }

    public function hasCustomerTypeOverride(): bool
    {
        return $this->filled('customer_type') && $this->user()?->can('pricing.admin.calculate');
    }

    public function hasDistanceOverride(): bool
    {
        return $this->has('distance_km') && $this->user()?->can('pricing.admin.calculate');
    }

    public function hasPolicyOverride(): bool
    {
        return $this->filled('pricing_policy_id') && $this->user()?->can('pricing.admin.calculate');
    }

    /**
     * @return array<string, list<string>>
     */
    private function prohibitedFieldRules(): array
    {
        return [
            'tax_rate' => ['prohibited'],
            'discount' => ['prohibited'],
            'discount_amount' => ['prohibited'],
            'minimum_fare' => ['prohibited'],
            'priority_multiplier' => ['prohibited'],
            'service_price' => ['prohibited'],
            'service_prices' => ['prohibited'],
            'base_rate' => ['prohibited'],
            'distance_tier_rate' => ['prohibited'],
            'final_total' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\Enum|\Illuminate\Validation\Rules\In>>
     */
    private function calculationFieldRules(): array
    {
        return [
            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
            'delivery_latitude' => ['required', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['required', 'numeric', 'between:-180,180'],
            'cargo_type' => ['required', Rule::enum(CargoType::class)],
            'trailer_type' => ['required', Rule::enum(TrailerType::class)],
            'cargo_weight' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'shipment_priority' => ['required', 'string', Rule::in(['standard', 'express', 'urgent', 'emergency'])],
            'border_crossing' => ['sometimes', 'boolean'],
            'hazmat' => ['sometimes', 'boolean'],
            'insurance_required' => ['sometimes', 'boolean'],
            'loading_waiting_time' => ['sometimes', 'integer', 'min:0'],
            'unloading_waiting_time' => ['sometimes', 'integer', 'min:0'],
            'traffic_delay' => ['sometimes', 'integer', 'min:0'],
            'customer_delay' => ['sometimes', 'integer', 'min:0'],
            'selected_service_ids' => ['sometimes', 'array'],
            'selected_service_ids.*' => ['integer', 'min:1'],
            'customer_company_id' => ['sometimes', 'uuid', 'exists:companies,id'],
        ];
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\Enum>>
     */
    private function overrideFieldRules(): array
    {
        return [
            'customer_type' => ['sometimes', Rule::enum(CustomerType::class)],
            'distance_km' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'pricing_policy_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
