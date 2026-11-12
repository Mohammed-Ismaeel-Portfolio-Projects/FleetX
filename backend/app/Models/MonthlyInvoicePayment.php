<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyInvoicePayment extends Model
{
    protected $fillable = [
        'monthly_invoice_id',
        'company_id',
        'amount',
        'currency',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MonthlyInvoice::class, 'monthly_invoice_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
