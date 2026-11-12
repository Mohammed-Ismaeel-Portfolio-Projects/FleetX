<?php

namespace App\Models;

use App\Enums\MonthlyInvoiceStatus;
use App\Services\Invoice\Exceptions\MonthlyInvoiceLifecycleException;
use App\Enums\InvoiceLifecycleErrorCode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Monthly billing invoice header (Phase 6a foundation).
 */
class MonthlyInvoice extends Model
{
    protected $fillable = [
        'company_id',
        'billing_period_start',
        'billing_period_end',
        'currency',
        'subtotal',
        'total',
        'status',
        'issued_at',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => MonthlyInvoiceStatus::class,
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (MonthlyInvoice $invoice): void {
            $originalStatus = $invoice->getOriginal('status');

            if ($originalStatus instanceof MonthlyInvoiceStatus) {
                $original = $originalStatus;
            } else {
                $original = MonthlyInvoiceStatus::tryFrom((string) $originalStatus);
            }

            if ($original === MonthlyInvoiceStatus::VOID) {
                throw new MonthlyInvoiceLifecycleException(
                    'Void invoices are immutable.',
                    409,
                    InvoiceLifecycleErrorCode::INVOICE_IMMUTABLE,
                );
            }

            if ($original === MonthlyInvoiceStatus::PAID) {
                throw new MonthlyInvoiceLifecycleException(
                    'Paid invoices are immutable.',
                    409,
                    InvoiceLifecycleErrorCode::INVOICE_IMMUTABLE,
                );
            }

            if ($original === MonthlyInvoiceStatus::FINALIZED) {
                $dirty = array_keys($invoice->getDirty());
                $allowedDirtyKeys = ['status', 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'paid_at'];

                $disallowedDirty = array_diff($dirty, $allowedDirtyKeys);

                if ($disallowedDirty !== []) {
                    throw new MonthlyInvoiceLifecycleException(
                        'Finalized invoices are immutable except for void/paid transitions and checkout session tracking.',
                        409,
                        InvoiceLifecycleErrorCode::INVOICE_IMMUTABLE,
                    );
                }

                if ($invoice->status !== MonthlyInvoiceStatus::FINALIZED
                    && $invoice->status !== MonthlyInvoiceStatus::VOID
                    && $invoice->status !== MonthlyInvoiceStatus::PAID) {
                    throw new MonthlyInvoiceLifecycleException(
                        'Invalid status transition for finalized invoice.',
                        409,
                        InvoiceLifecycleErrorCode::INVOICE_IMMUTABLE,
                    );
                }
            }
        });

        static::deleting(function (MonthlyInvoice $invoice): void {
            if ($invoice->status !== MonthlyInvoiceStatus::DRAFT) {
                throw new MonthlyInvoiceLifecycleException(
                    'Only draft invoices may be deleted.',
                    409,
                    InvoiceLifecycleErrorCode::INVOICE_IMMUTABLE,
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MonthlyInvoicePayment::class, 'monthly_invoice_id');
    }

    public function isBillingImmutable(): bool
    {
        return in_array($this->status, [
            MonthlyInvoiceStatus::FINALIZED,
            MonthlyInvoiceStatus::VOID,
            MonthlyInvoiceStatus::PAID,
        ], true);
    }

    /**
     * Scope invoices to a billing customer company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Find invoice for company + billing period + currency identity.
     */
    public static function findForBillingIdentity(
        string $companyId,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
        string $currency,
    ): ?self {
        return self::query()
            ->where('company_id', $companyId)
            ->whereDate('billing_period_start', $billingPeriodStart->toDateString())
            ->whereDate('billing_period_end', $billingPeriodEnd->toDateString())
            ->where('currency', $currency)
            ->first();
    }
}
