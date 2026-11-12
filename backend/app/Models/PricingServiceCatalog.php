<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingServiceCatalog extends Model
{
    use HasFactory;

    protected $table = 'pricing_service_catalog';

    protected $fillable = [
        'pricing_policy_id',
        'name',
        'description',
        'price',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }
}
