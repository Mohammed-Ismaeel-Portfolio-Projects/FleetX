<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'radius',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius' => 'decimal:2',
        ];
    }

    public function pickupOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'pickup_location_id');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'delivery_location_id');
    }
}
