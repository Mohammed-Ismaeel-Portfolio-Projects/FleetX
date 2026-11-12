<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'pricing_policy_id',
        'code',
        'name',
        'fee_type',
        'amount',
        'condition_field',
        'condition_value',
        'max_amount',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'max_amount' => 'decimal:4',
            'enabled' => 'boolean',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
