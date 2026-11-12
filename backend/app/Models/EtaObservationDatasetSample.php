<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtaObservationDatasetSample extends Model
{
    use HasFactory;

    protected $table = 'eta_observation_dataset_samples';

    protected $fillable = [
        'shipment_id',
        'assignment_id',
        'tracking_log_id',
        'observation_time',
        'base_eta_seconds',
        'base_distance_meters',
        'baseline_routing_provider',
        'remaining_distance_meters',
        'total_distance_meters',
        'distance_travelled_meters',
        'completion_percentage',
        'segment_index',
        'polyline_index',
        'current_latitude',
        'current_longitude',
        'current_speed_kmh',
        'heading',
        'gps_accuracy',
        'movement_status',
        'driver_id',
        'driver_years_experience',
        'driver_score',
        'vehicle_id',
        'vehicle_gross_weight_kg',
        'cargo_weight',
        'cargo_type',
        'temperature_controlled',
        'hazardous_cargo',
        'hour_of_day',
        'day_of_week',
        'actual_total_duration_seconds',
        'actual_remaining_seconds',
        'baseline_remaining_seconds',
        'residual_seconds',
    ];

    protected function casts(): array
    {
        return [
            'observation_time' => 'datetime',
            'base_eta_seconds' => 'integer',
            'base_distance_meters' => 'integer',
            'remaining_distance_meters' => 'decimal:2',
            'total_distance_meters' => 'decimal:2',
            'distance_travelled_meters' => 'decimal:2',
            'completion_percentage' => 'decimal:2',
            'segment_index' => 'integer',
            'polyline_index' => 'integer',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'current_speed_kmh' => 'decimal:2',
            'heading' => 'decimal:2',
            'gps_accuracy' => 'decimal:2',
            'driver_years_experience' => 'integer',
            'driver_score' => 'decimal:2',
            'vehicle_gross_weight_kg' => 'integer',
            'cargo_weight' => 'decimal:2',
            'temperature_controlled' => 'boolean',
            'hazardous_cargo' => 'boolean',
            'hour_of_day' => 'integer',
            'day_of_week' => 'integer',
            'actual_total_duration_seconds' => 'integer',
            'actual_remaining_seconds' => 'decimal:2',
            'baseline_remaining_seconds' => 'decimal:2',
            'residual_seconds' => 'decimal:2',
        ];
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

    public function trackingLog(): BelongsTo
    {
        return $this->belongsTo(TrackingLog::class);
    }
}
