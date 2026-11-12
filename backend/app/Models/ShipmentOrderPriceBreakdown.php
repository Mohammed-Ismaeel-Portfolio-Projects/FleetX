<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentOrderPriceBreakdown extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_order_id',
        'pricing_policy_id',
        'breakdown_json',
        'final_total',
        'currency',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'breakdown_json' => 'array',
            'final_total' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function shipmentOrder(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrder::class);
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
