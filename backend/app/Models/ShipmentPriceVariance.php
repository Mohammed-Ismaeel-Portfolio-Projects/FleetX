<?php

namespace App\Models;

use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentPriceVariance extends Model
{
    protected $fillable = [
        'shipment_final_price_breakdown_id',
        'factor_code',
        'baseline_value',
        'actual_value',
        'delta_value',
        'delta_unit',
        'threshold_value',
        'threshold_type',
        'threshold_scope',
        'material',
        'billable',
        'price_impact',
        'reason_code',
        'customer_message',
        'internal_message',
        'data_source',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'factor_code' => VarianceFactorCode::class,
            'baseline_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'delta_value' => 'decimal:4',
            'threshold_value' => 'decimal:4',
            'threshold_type' => VarianceThresholdType::class,
            'material' => 'boolean',
            'billable' => 'boolean',
            'price_impact' => 'decimal:2',
        ];
    }

    public function finalPriceBreakdown(): BelongsTo
    {
        return $this->belongsTo(ShipmentFinalPriceBreakdown::class, 'shipment_final_price_breakdown_id');
    }
}
