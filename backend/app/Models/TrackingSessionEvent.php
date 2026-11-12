<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingSessionEvent extends Model
{
    use HasFactory;

    public const EVENT_STARTED = 'STARTED';
    public const EVENT_PAUSED = 'PAUSED';
    public const EVENT_RESUMED = 'RESUMED';
    public const EVENT_STOPPED = 'STOPPED';
    public const EVENT_SPEED_OVERRIDE = 'SPEED_OVERRIDE';
    public const EVENT_SUDDEN_STOP_TRIGGERED = 'SUDDEN_STOP_TRIGGERED';
    public const EVENT_ROUTE_DEVIATION_TRIGGERED = 'ROUTE_DEVIATION_TRIGGERED';
    public const EVENT_OVERRIDE_RESET = 'OVERRIDE_RESET';

    protected $fillable = [
        'tracking_session_id',
        'event_type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function trackingSession(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class);
    }
}
