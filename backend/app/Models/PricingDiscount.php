<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'pricing_policy_id',
        'customer_type',
        'discount_type',
        'amount',
        'max_discount',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'max_discount' => 'decimal:4',
            'enabled' => 'boolean',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
