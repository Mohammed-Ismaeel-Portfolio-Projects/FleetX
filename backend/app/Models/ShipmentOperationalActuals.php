<?php

namespace App\Models;

use App\Enums\OperationalActualsCaptureSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authoritative persisted operational actuals for one executed Shipment (Phase 5.5a).
 */
class ShipmentOperationalActuals extends Model
{
    protected $fillable = [
        'shipment_id',
        'actual_distance_km',
        'actual_cargo_weight',
        'actual_loading_wait_minutes',
        'actual_unloading_wait_minutes',
        'actual_traffic_delay_minutes',
        'actual_customer_delay_minutes',
        'actual_border_crossing',
        'actual_hazmat',
        'actual_service_ids',
        'actual_trailer_type',
        'capture_source',
        'captured_at',
        'captured_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'actual_distance_km' => 'decimal:3',
            'actual_cargo_weight' => 'decimal:2',
            'actual_loading_wait_minutes' => 'integer',
            'actual_unloading_wait_minutes' => 'integer',
            'actual_traffic_delay_minutes' => 'integer',
            'actual_customer_delay_minutes' => 'integer',
            'actual_border_crossing' => 'boolean',
            'actual_hazmat' => 'boolean',
            'actual_service_ids' => 'array',
            'capture_source' => OperationalActualsCaptureSource::class,
            'captured_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }
}
