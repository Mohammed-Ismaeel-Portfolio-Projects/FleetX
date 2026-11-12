<?php

namespace App\Models;

use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingVarianceRule extends Model
{
    protected $fillable = [
        'pricing_policy_id',
        'factor_code',
        'threshold_type',
        'threshold_value',
        'applies_to_increase',
        'applies_to_decrease',
        'enabled',
        'customer_message_template',
        'internal_message_template',
    ];

    protected function casts(): array
    {
        return [
            'factor_code' => VarianceFactorCode::class,
            'threshold_type' => VarianceThresholdType::class,
            'threshold_value' => 'decimal:4',
            'applies_to_increase' => 'boolean',
            'applies_to_decrease' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
