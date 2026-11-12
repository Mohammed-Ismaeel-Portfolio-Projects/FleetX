<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Driver device push registration (D12). Token is a credential — do not expose on list APIs.
 */
class DriverDevice extends Model
{
    protected $fillable = [
        'driver_id',
        'platform',
        'push_token',
        'device_id',
        'is_active',
        'last_seen_at',
        'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
