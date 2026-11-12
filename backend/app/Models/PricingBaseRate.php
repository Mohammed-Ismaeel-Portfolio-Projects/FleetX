<?php

namespace App\Models;

use App\Enums\CargoType;
use App\Enums\TrailerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingBaseRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'pricing_policy_id',
        'cargo_type',
        'trailer_type',
        'rate_per_km',
    ];

    protected function casts(): array
    {
        return [
            'cargo_type' => CargoType::class,
            'trailer_type' => TrailerType::class,
            'rate_per_km' => 'decimal:4',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
