<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'company_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier that will be stored in the "sub" claim of the JWT.
     * Required by the JWTSubject contract — auth wiring, not business logic.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Custom claims added to every token issued for this user.
     * Required by the JWTSubject contract — auth wiring, not business logic.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role?->name,
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Linked Driver employee record (role=driver).
     * Null when the user is not a linked driver account.
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * Linked customer Company (role=client).
     * Null when the user is not a linked client account.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function submittedShipmentOrders(): HasMany
    {
        return $this->hasMany(ShipmentOrder::class, 'submitted_by_user_id');
    }

    public function assignmentsCreated(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assigned_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'generated_by');
    }

    /**
     * Determine if the user belongs to FleetX internal organization.
     */
    public function isFleetXInternal(): bool
    {
        if ($this->company_id === null) {
            return true;
        }

        $this->loadMissing('company');

        if ($this->company?->is_active === false) {
            return false;
        }

        return $this->company?->isInternal() ?? false;
    }

    /**
     * Determine if the user belongs to a partner carrier company.
     */
    public function isPartnerCarrierUser(): bool
    {
        if ($this->company_id === null) {
            return false;
        }

        $this->loadMissing('company');

        if ($this->company?->is_active === false) {
            return false;
        }

        return $this->company?->isPartnerCarrier() ?? false;
    }

    /**
     * Determine if the user belongs to a client/customer company.
     */
    public function isClientUser(): bool
    {
        if ($this->company_id === null) {
            return false;
        }

        $this->loadMissing('company');

        if ($this->company?->is_active === false) {
            return false;
        }

        return $this->company?->isClient() ?? false;
    }
}
