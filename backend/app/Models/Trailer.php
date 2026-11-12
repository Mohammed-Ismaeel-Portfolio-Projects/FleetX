<?php

namespace App\Models;

use App\Traits\HasAudit;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Trailer Model
 * 
 * Represents a FleetX trailer asset.
 * Trailers are FleetX-owned assets and are not assigned to customer companies.
 * 
 * @property string $id UUID primary key
 * @property string $trailer_code FleetX trailer identifier
 * @property string $fleet_number Internal fleet number
 * @property string $plate_number Legal registration plate number
 * @property string $trailer_type Type of trailer (e.g., Dry Van, Refrigerated)
 * @property string $manufacturer Trailer manufacturer
 * @property string|null $model Trailer model
 * @property int|null $production_year Year of manufacture
 * @property int|null $axle_count Number of axles
 * @property int|null $payload_capacity_kg Maximum payload capacity in kilograms
 * @property float|null $length_m Trailer length in meters
 * @property float|null $width_m Trailer width in meters
 * @property float|null $height_m Trailer height in meters
 * @property float|null $cargo_volume_m3 Cargo volume in cubic meters
 * @property int|null $current_odometer Current odometer reading in kilometers
 * @property string|null $gps_device_identifier GPS device identifier
 * @property \Illuminate\Support\Carbon|null $insurance_expiry_date Insurance expiry date
 * @property \Illuminate\Support\Carbon|null $inspection_expiry_date Inspection expiry date
 * @property \Illuminate\Support\Carbon|null $registration_expiry_date Registration expiry date
 * @property int $status_id Status reference
 * @property bool $is_active Quick active/inactive flag
 * @property string|null $notes Internal notes
 * @property string|null $created_by User who created the record
 * @property string|null $updated_by User who last updated the record
 * @property \Illuminate\Support\Carbon $created_at Record creation timestamp
 * @property \Illuminate\Support\Carbon $updated_at Record last update timestamp
 * @property \Illuminate\Support\Carbon|null $deleted_at Soft delete timestamp
 */
class Trailer extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;
    use HasAudit;

    protected $table = 'trailers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'trailer_code',
        'fleet_number',
        'plate_number',
        'trailer_type',
        'company_id',
        'ownership_type',
        'manufacturer',
        'model',
        'production_year',
        'axle_count',
        'payload_capacity_kg',
        'length_m',
        'width_m',
        'height_m',
        'cargo_volume_m3',
        'current_odometer',
        'gps_device_identifier',
        'insurance_expiry_date',
        'inspection_expiry_date',
        'registration_expiry_date',
        'status_id',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'production_year' => 'integer',
            'axle_count' => 'integer',
            'payload_capacity_kg' => 'integer',
            'current_odometer' => 'integer',
            'length_m' => 'decimal:2',
            'width_m' => 'decimal:2',
            'height_m' => 'decimal:2',
            'cargo_volume_m3' => 'decimal:2',
            'is_active' => 'boolean',
            'insurance_expiry_date' => 'date',
            'inspection_expiry_date' => 'date',
            'registration_expiry_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
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

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Assignment::class, 'trailer_id');
    }

    public function activeAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Assignment::class, 'trailer_id')
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }
}
