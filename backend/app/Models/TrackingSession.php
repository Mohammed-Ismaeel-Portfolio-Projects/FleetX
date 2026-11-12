<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingSession extends Model
{
    /** @use HasFactory<\Database\Factories\TrackingSessionFactory> */
    use HasFactory, HasUuid;

    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_PAUSED = 'PAUSED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_TERMINATED = 'TERMINATED';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $attributes = [
        'status' => self::STATUS_INACTIVE,
        'speed_multiplier' => 1.00,
        'forced_deceleration' => false,
        'forced_route_deviation' => false,
    ];

    protected $fillable = [
        'shipment_order_id',
        'shipment_id',
        'assignment_id',
        'driver_id',
        'vehicle_id',
        'status',
        'speed_multiplier',
        'forced_speed_kmh',
        'forced_deceleration',
        'forced_route_deviation',
        'started_at',
        'paused_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'speed_multiplier' => 'decimal:2',
            'forced_speed_kmh' => 'decimal:2',
            'forced_deceleration' => 'boolean',
            'forced_route_deviation' => 'boolean',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function shipmentOrder(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrder::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrackingSessionEvent::class)->orderByDesc('id');
    }
}
