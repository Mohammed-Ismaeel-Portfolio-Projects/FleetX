<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Legacy per-shipment invoice model (pre-monthly billing architecture).
 *
 * Not wired to Company (no company_id column). No controllers, routes, or
 * creation logic consume this model today. New billing uses MonthlyInvoice.
 */
class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'total_cost',
        'currency',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
