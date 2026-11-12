<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingState extends Model
{
    use HasUuids;

    protected $table = 'tracking_states';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'assignment_id',
        'shipment_id',
        'vehicle_id',
        'driver_id',
        'trailer_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'accuracy',
        'movement_status',
        'online_status',
        'signal_quality',
        'last_update_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'last_update_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class, 'trailer_id');
    }
}
