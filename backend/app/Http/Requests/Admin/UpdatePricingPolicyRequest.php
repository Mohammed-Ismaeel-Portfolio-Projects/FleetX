<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesPricingPolicyConfiguration;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingPolicyRequest extends FormRequest
{
    use ValidatesPricingPolicyConfiguration;

    public function authorize(): bool
    {
        return $this->user()?->can('pricing.policy.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            $this->pricingPolicyProhibitedFields(),
            $this->pricingPolicyFieldRules(requireAll: true),
        );
    }
}
