<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingRule extends Model
{
    /** @use HasFactory<\Database\Factories\PricingRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'cost_per_km',
        'cost_per_kg',
        'base_cost',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_km' => 'decimal:2',
            'cost_per_kg' => 'decimal:2',
            'base_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
