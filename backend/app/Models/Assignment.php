<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    /** @use HasFactory<\Database\Factories\AssignmentFactory> */
    use HasFactory, SoftDeletes, HasAudit;

    protected $fillable = [
        'shipment_id',
        'assignment_code',
        'driver_id',
        'vehicle_id',
        'trailer_id',
        'assigned_by',
        'status_id',
        'assigned_at',
        'ended_at',
        'is_active',
        'execution_phase',
        'engine_enrollment_status',
        'engine_enrolled_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
            'engine_enrolled_at' => 'datetime',
            'is_active' => 'boolean',
            'status_id' => 'integer',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(AssignmentEvaluation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Assignments enrolled for SimulationRuntime (C2.9-C2.8 Step 3).
     */
    public function scopeEngineEnrolled($query)
    {
        return $query->whereIn(
            'engine_enrollment_status',
            \App\Support\EngineEnrollmentStatus::simulationEligible()
        );
    }

    public function isEngineEnrolled(): bool
    {
        return \App\Support\EngineEnrollmentStatus::isSimulationEligible($this->engine_enrollment_status);
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForVehicle($query, $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeForTrailer($query, $trailerId)
    {
        return $query->where('trailer_id', $trailerId);
    }

    public function isCompleted(): bool
    {
        return $this->status_id === Status::where('name', 'Completed')->where('type', 'assignment')->first()?->id;
    }

    public function isCancelled(): bool
    {
        return $this->status_id === Status::where('name', 'Cancelled')->where('type', 'assignment')->first()?->id;
    }

    public function isInOperation(): bool
    {
        return $this->status_id === Status::where('name', 'In Operation')->where('type', 'assignment')->first()?->id;
    }

    public function trackingSession(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TrackingSession::class);
    }
}
