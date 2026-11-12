<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingWaitingConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'pricing_policy_id',
        'loading_free_minutes',
        'unloading_free_minutes',
        'rate_per_15min',
        'max_charge',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'loading_free_minutes' => 'integer',
            'unloading_free_minutes' => 'integer',
            'rate_per_15min' => 'decimal:4',
            'max_charge' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
