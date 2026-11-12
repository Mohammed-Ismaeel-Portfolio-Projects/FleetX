<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingWeightConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'pricing_policy_id',
        'threshold_tons',
        'charge_per_ton',
    ];

    protected function casts(): array
    {
        return [
            'threshold_tons' => 'decimal:3',
            'charge_per_ton' => 'decimal:2',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
