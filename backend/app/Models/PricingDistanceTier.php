<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingDistanceTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'pricing_policy_id',
        'min_distance_km',
        'max_distance_km',
        'rate_per_km',
    ];

    protected function casts(): array
    {
        return [
            'min_distance_km' => 'decimal:2',
            'max_distance_km' => 'decimal:2',
            'rate_per_km' => 'decimal:4',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
