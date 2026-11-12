<?php

namespace App\Models;

use App\Enums\InvoiceLifecycleErrorCode;
use App\Enums\MonthlyInvoiceStatus;
use App\Services\Invoice\Exceptions\MonthlyInvoiceLifecycleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice line referencing a finalized shipment price snapshot (Phase 6a foundation).
 */
class InvoiceLineItem extends Model
{
    protected $fillable = [
        'monthly_invoice_id',
        'shipment_id',
        'shipment_final_price_breakdown_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (InvoiceLineItem $lineItem): void {
            self::assertMutableParentInvoice($lineItem);
        });

        static::deleting(function (InvoiceLineItem $lineItem): void {
            self::assertMutableParentInvoice($lineItem);
        });
    }

    private static function assertMutableParentInvoice(InvoiceLineItem $lineItem): void
    {
        $invoice = $lineItem->relationLoaded('monthlyInvoice')
            ? $lineItem->monthlyInvoice
            : $lineItem->monthlyInvoice()->first();

        if ($invoice !== null && $invoice->status !== MonthlyInvoiceStatus::DRAFT) {
            throw new MonthlyInvoiceLifecycleException(
                'Invoice line items are immutable after invoice finalization or void.',
                409,
                InvoiceLifecycleErrorCode::INVOICE_LINE_ITEM_IMMUTABLE,
            );
        }
    }

    public function monthlyInvoice(): BelongsTo
    {
        return $this->belongsTo(MonthlyInvoice::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function finalPriceBreakdown(): BelongsTo
    {
        return $this->belongsTo(ShipmentFinalPriceBreakdown::class, 'shipment_final_price_breakdown_id');
    }
}
