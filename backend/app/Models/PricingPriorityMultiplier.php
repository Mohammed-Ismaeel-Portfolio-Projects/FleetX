<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPriorityMultiplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'pricing_policy_id',
        'priority_level',
        'multiplier',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
