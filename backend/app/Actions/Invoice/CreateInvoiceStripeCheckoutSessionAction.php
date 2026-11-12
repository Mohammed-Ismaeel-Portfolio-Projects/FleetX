<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Core\ApiResult;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\User;
use App\Services\Invoice\StripePaymentService;

class CreateInvoiceStripeCheckoutSessionAction
{
    public function __construct(
        private readonly StripePaymentService $stripePaymentService,
    ) {}

    public function execute(User $user, int|string $monthlyInvoiceId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden: User does not belong to a billing company.', 403);
        }

        $invoice = MonthlyInvoice::query()
            ->forCompany($companyId)
            ->find($monthlyInvoiceId);

        if ($invoice === null) {
            return ApiResult::notFound('Monthly invoice not found.');
        }

        if ($invoice->status === MonthlyInvoiceStatus::PAID) {
            return ApiResult::error('Invoice is already paid.', 409);
        }

        if ($invoice->status === MonthlyInvoiceStatus::DRAFT) {
            return ApiResult::error('Draft invoices cannot be paid. Only finalized invoices can be paid.', 422);
        }

        if ($invoice->status === MonthlyInvoiceStatus::VOID) {
            return ApiResult::error('Void invoices cannot be paid.', 409);
        }

        if ($invoice->status !== MonthlyInvoiceStatus::FINALIZED) {
            return ApiResult::error('Invoice must be finalized before payment.', 422);
        }

        $requestReturnUrl = request()->input('return_url') ?? request()->header('X-Return-Url');
        if ($requestReturnUrl && is_string($requestReturnUrl)) {
            $separator = str_contains($requestReturnUrl, '?') ? '&' : '?';
            $successUrl = "{$requestReturnUrl}{$separator}payment=success&invoice={$invoice->id}&session_id={CHECKOUT_SESSION_ID}";
            $cancelUrl = "{$requestReturnUrl}{$separator}payment=cancelled&invoice={$invoice->id}";
        } else {
            $baseUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            $successUrl = "{$baseUrl}/client/invoices?payment=success&invoice={$invoice->id}&session_id={CHECKOUT_SESSION_ID}";
            $cancelUrl = "{$baseUrl}/client/invoices?payment=cancelled&invoice={$invoice->id}";
        }

        try {
            $session = $this->stripePaymentService->createCheckoutSession($invoice, $successUrl, $cancelUrl);

            $invoice->stripe_checkout_session_id = $session->id;
            $invoice->save();

            return ApiResult::success([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'invoice_id' => $invoice->id,
                'status' => $invoice->status->value,
            ], 'Stripe checkout session created successfully');
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to create Stripe checkout session: ' . $e->getMessage(), 500);
        }
    }
}
