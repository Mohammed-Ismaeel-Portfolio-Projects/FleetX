<?php

namespace App\Models;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\FinalPriceCalculationTrigger;
use App\Enums\FinalPriceChangeDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShipmentFinalPriceBreakdown extends Model
{
    protected $fillable = [
        'shipment_id',
        'shipment_order_id',
        'submission_price_breakdown_id',
        'pricing_policy_id',
        'operational_actuals_id',
        'breakdown_json',
        'submission_total',
        'final_total',
        'price_delta',
        'price_delta_percentage',
        'change_direction',
        'material_change',
        'currency',
        'calculated_at',
        'calculated_by_user_id',
        'calculation_trigger',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'breakdown_json' => 'array',
            'submission_total' => 'decimal:2',
            'final_total' => 'decimal:2',
            'price_delta' => 'decimal:2',
            'price_delta_percentage' => 'decimal:4',
            'change_direction' => FinalPriceChangeDirection::class,
            'material_change' => 'boolean',
            'calculated_at' => 'datetime',
            'calculation_trigger' => FinalPriceCalculationTrigger::class,
            'status' => FinalPriceBreakdownStatus::class,
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function shipmentOrder(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrder::class);
    }

    public function submissionPriceBreakdown(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrderPriceBreakdown::class, 'submission_price_breakdown_id');
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }

    public function operationalActuals(): BelongsTo
    {
        return $this->belongsTo(ShipmentOperationalActuals::class, 'operational_actuals_id');
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by_user_id');
    }

    public function variances(): HasMany
    {
        return $this->hasMany(ShipmentPriceVariance::class)->orderBy('sort_order');
    }

    public function invoiceLineItem(): HasOne
    {
        return $this->hasOne(InvoiceLineItem::class, 'shipment_final_price_breakdown_id');
    }

    public function isFinalized(): bool
    {
        return $this->status === FinalPriceBreakdownStatus::FINALIZED;
    }
}
