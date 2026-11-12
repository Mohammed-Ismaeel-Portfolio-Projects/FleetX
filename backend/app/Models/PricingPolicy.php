<?php

namespace App\Models;

use App\Enums\PricingPolicyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'currency',
        'effective_from',
        'effective_until',
        'priority',
        'tax_rate',
        'tax_inclusive',
        'minimum_fare',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PricingPolicyStatus::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'priority' => 'integer',
            'tax_rate' => 'decimal:4',
            'tax_inclusive' => 'boolean',
            'minimum_fare' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function baseRates(): HasMany
    {
        return $this->hasMany(PricingBaseRate::class);
    }

    public function distanceTiers(): HasMany
    {
        return $this->hasMany(PricingDistanceTier::class);
    }

    public function weightConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PricingWeightConfig::class);
    }

    public function waitingConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PricingWaitingConfig::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(PricingFee::class);
    }

    public function priorityMultipliers(): HasMany
    {
        return $this->hasMany(PricingPriorityMultiplier::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(PricingDiscount::class);
    }

    public function serviceCatalogItems(): HasMany
    {
        return $this->hasMany(PricingServiceCatalog::class);
    }

    public function varianceRules(): HasMany
    {
        return $this->hasMany(PricingVarianceRule::class);
    }
}
