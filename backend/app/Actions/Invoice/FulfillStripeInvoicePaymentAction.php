<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Core\ApiResult;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\MonthlyInvoicePayment;
use Illuminate\Support\Facades\DB;

class FulfillStripeInvoicePaymentAction
{
    public function execute(array $eventPayload): ApiResult
    {
        $eventType = $eventPayload['type'] ?? null;

        if ($eventType !== 'checkout.session.completed') {
            return ApiResult::success(null, 'Event type ignored');
        }

        $session = $eventPayload['data']['object'] ?? [];
        $sessionId = $session['id'] ?? null;
        $paymentIntentId = $session['payment_intent'] ?? null;
        $metadata = $session['metadata'] ?? [];

        $invoiceId = $metadata['fleetx_monthly_invoice_id'] ?? $session['client_reference_id'] ?? null;
        $metadataCompanyId = $metadata['fleetx_company_id'] ?? null;

        if (! $sessionId || ! $invoiceId) {
            return ApiResult::error('Missing checkout session or invoice identity in webhook payload', 422);
        }

        return DB::transaction(function () use ($invoiceId, $metadataCompanyId, $sessionId, $paymentIntentId) {
            /** @var MonthlyInvoice|null $invoice */
            $invoice = MonthlyInvoice::query()
                ->lockForUpdate()
                ->find($invoiceId);

            if ($invoice === null) {
                return ApiResult::notFound("Monthly invoice #{$invoiceId} not found");
            }

            // Consistency check if company identity is present in metadata
            if ($metadataCompanyId && (string) $invoice->company_id !== (string) $metadataCompanyId) {
                return ApiResult::error("Metadata company mismatch for invoice #{$invoiceId}", 422);
            }

            // IDEMPOTENCY CHECK 1: Invoice is already PAID
            if ($invoice->status === MonthlyInvoiceStatus::PAID) {
                return ApiResult::success($invoice, 'Invoice is already paid (idempotent)');
            }

            // IDEMPOTENCY CHECK 2: Payment transaction record already exists with status succeeded
            $existingPayment = MonthlyInvoicePayment::query()
                ->where('stripe_checkout_session_id', $sessionId)
                ->where('status', 'succeeded')
                ->first();

            if ($existingPayment !== null) {
                return ApiResult::success($invoice, 'Payment already recorded (idempotent)');
            }

            // Ensure invoice is FINALIZED before transitioning to PAID
            if ($invoice->status !== MonthlyInvoiceStatus::FINALIZED) {
                return ApiResult::error("Invoice #{$invoiceId} cannot be paid from status '{$invoice->status->value}'", 409);
            }

            $paidAt = now();

            // 1. Transition invoice to PAID
            $invoice->status = MonthlyInvoiceStatus::PAID;
            $invoice->paid_at = $paidAt;
            $invoice->stripe_checkout_session_id = $sessionId;
            $invoice->stripe_payment_intent_id = $paymentIntentId;
            $invoice->save();

            // 2. Audit record in monthly_invoice_payments
            MonthlyInvoicePayment::updateOrCreate(
                ['stripe_checkout_session_id' => $sessionId],
                [
                    'monthly_invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                    'amount' => $invoice->total,
                    'currency' => $invoice->currency,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'status' => 'succeeded',
                    'paid_at' => $paidAt,
                ]
            );

            return ApiResult::success($invoice, 'Invoice payment fulfilled successfully');
        });
    }
}
