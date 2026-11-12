<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    /** @use HasFactory<\Database\Factories\DriverFactory> */
    use HasFactory, SoftDeletes, HasAudit;

    protected $fillable = [
        'user_id',
        'company_id',
        'ownership_type',
        'employee_number',
        'first_name',
        'last_name',
        'date_of_birth',
        'nationality',
        'phone',
        'email',
        'address',
        'employment_date',
        'license_number',
        'license_class',
        'license_expiry_date',
        'medical_certificate_expiry',
        'years_of_experience',
        'status_id',
        'is_active',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'employment_date' => 'date',
            'license_expiry_date' => 'date',
            'medical_certificate_expiry' => 'date',
            'years_of_experience' => 'integer',
            'status_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
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

    /** Auth identity for Driver App — nullable when unlinked. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function driverScore(): HasOne
    {
        return $this->hasOne(DriverScore::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->where('is_active', true);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(DriverDevice::class);
    }

    public function trackingState(): HasOne
    {
        return $this->hasOne(TrackingState::class, 'driver_id');
    }

    /** Historical evaluation snapshots keyed by evaluation.driver_id (DRIVER-EVAL). */
    public function assignmentEvaluations(): HasMany
    {
        return $this->hasMany(AssignmentEvaluation::class);
    }

    public function pairedVehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class, 'driver_id');
    }
}
