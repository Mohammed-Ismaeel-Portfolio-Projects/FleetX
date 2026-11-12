<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    /** @use HasFactory<\Database\Factories\RouteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'shipment_id',
        'total_distance',
        'estimated_time',
    ];

    protected function casts(): array
    {
        return [
            'total_distance' => 'decimal:2',
            'estimated_time' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function routePoints(): HasMany
    {
        return $this->hasMany(RoutePoint::class);
    }
}
