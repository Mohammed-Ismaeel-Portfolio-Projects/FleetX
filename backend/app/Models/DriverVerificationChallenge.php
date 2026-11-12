<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverVerificationChallenge extends Model
{
    use HasUuids;

    protected $table = 'driver_verification_challenges';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'shipment_id',
        'assignment_id',
        'event',
        'expires_at',
        'consumed_at',
        'consumed_by_user_id',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_user_id');
    }

    public function isExpired(?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? now();

        return $this->expires_at !== null && $this->expires_at->lte($now);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isOpen(?\DateTimeInterface $now = null): bool
    {
        return ! $this->isConsumed() && ! $this->isRevoked() && ! $this->isExpired($now);
    }

    public function qrPayload(): string
    {
        $scheme = rtrim((string) config('driver_verification.qr_scheme', 'fleetx://v1/verify'), '?&');

        return $scheme.'?c='.$this->id;
    }
}
