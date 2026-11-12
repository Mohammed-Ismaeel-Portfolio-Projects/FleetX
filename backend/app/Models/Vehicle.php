<?php

namespace App\Models;

use App\Traits\HasAudit;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;
    use HasAudit;

    protected $table = 'vehicles';

    protected $keyType = 'string';

    public $incrementing = false;

    public const ROUTING_CLASS_VAN = 'van';
    public const ROUTING_CLASS_LIGHT_TRUCK = 'light_truck';
    public const ROUTING_CLASS_HEAVY_TRUCK = 'heavy_truck';

    protected $fillable = [
        'operational_code',
        'fleet_number',
        'plate_number',
        'vin',
        'manufacturer',
        'model',
        'production_year',
        'vehicle_type_id',
        'company_id',
        'ownership_type',
        'driver_id',
        'routing_class',
        'fuel_type_id',
        'transmission',
        'axle_count',
        'payload_capacity_kg',
        'gross_weight_kg',
        'height_m',
        'width_m',
        'length_m',
        'fuel_tank_capacity_l',
        'current_odometer',
        'status_id',
        'is_active',
        'gps_device_identifier',
        'insurance_expiry_date',
        'registration_expiry_date',
        'inspection_expiry_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'specifications',
    ];

    protected function casts(): array
    {
        return [
            'production_year' => 'integer',
            'axle_count' => 'integer',
            'payload_capacity_kg' => 'integer',
            'gross_weight_kg' => 'integer',
            'height_m' => 'float',
            'width_m' => 'float',
            'length_m' => 'float',
            'fuel_tank_capacity_l' => 'integer',
            'current_odometer' => 'integer',
            'is_active' => 'boolean',
            'insurance_expiry_date' => 'date',
            'registration_expiry_date' => 'date',
            'inspection_expiry_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Truck routing specifications used by the routing pipeline.
     *
     * @return array{weight_kg: int|null, height_m: float|null, width_m: float|null, length_m: float|null}
     */
    public function getSpecificationsAttribute(): array
    {
        return [
            'weight_kg' => $this->gross_weight_kg,
            'height_m' => $this->height_m,
            'width_m' => $this->width_m,
            'length_m' => $this->length_m,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function isInternalFleet(): bool
    {
        return $this->ownership_type === 'internal' || $this->ownership_type === null;
    }

    public function isPartnerFleet(): bool
    {
        if ($this->ownership_type === 'partner') {
            return true;
        }

        if ($this->company_id !== null) {
            return $this->company?->isPartnerCarrier() ?? true;
        }

        return false;
    }

    public function scopeInternalFleet($query)
    {
        return $query->where('ownership_type', 'internal');
    }

    public function scopePartnerFleet($query, $companyId = null)
    {
        $query->where('ownership_type', 'partner');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function fuelType(): BelongsTo
    {
        return $this->belongsTo(FuelType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(Assignment::class)->where('is_active', true);
    }

    public function trackingState()
    {
        return $this->hasOne(TrackingState::class, 'vehicle_id');
    }

    public function trackingSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrackingSession::class);
    }
}
