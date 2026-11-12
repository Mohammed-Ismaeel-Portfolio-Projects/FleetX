<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Models\MonthlyInvoice;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Event as StripeEvent;
use Stripe\Stripe;
use Stripe\Webhook as StripeWebhook;

class StripePaymentService
{
    public function __construct()
    {
        $secretKey = config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY') ?? 'sk_test_mock';
        Stripe::setApiKey($secretKey);
    }

    public function createCheckoutSession(MonthlyInvoice $invoice, string $successUrl, string $cancelUrl): StripeCheckoutSession
    {
        $amountCents = (int) round(((float) $invoice->total) * 100);
        $secretKey = config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY');

        if ($secretKey === null || $secretKey === 'sk_test_mock' || app()->environment('testing')) {
            $mockId = 'cs_test_' . bin2hex(random_bytes(12));
            return StripeCheckoutSession::constructFrom([
                'id' => $mockId,
                'object' => 'checkout.session',
                'url' => "https://checkout.stripe.com/c/pay/{$mockId}",
                'payment_status' => 'unpaid',
                'status' => 'open',
                'client_reference_id' => (string) $invoice->id,
                'metadata' => [
                    'fleetx_monthly_invoice_id' => (string) $invoice->id,
                    'fleetx_company_id' => (string) $invoice->company_id,
                ],
            ]);
        }

        return StripeCheckoutSession::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($invoice->currency),
                    'product_data' => [
                        'name' => "FleetX Monthly Invoice #{$invoice->id}",
                        'description' => "Billing period: {$invoice->billing_period_start->toDateString()} to {$invoice->billing_period_end->toDateString()}",
                    ],
                    'unit_amount' => $amountCents,
                ],
                'quantity' => 1,
            ]],
            'client_reference_id' => (string) $invoice->id,
            'metadata' => [
                'fleetx_monthly_invoice_id' => (string) $invoice->id,
                'fleetx_company_id' => (string) $invoice->company_id,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    public function constructWebhookEvent(string $payload, string $sigHeader, ?string $webhookSecret = null): StripeEvent
    {
        $secret = $webhookSecret ?? config('services.stripe.webhook_secret') ?? env('STRIPE_WEBHOOK_SECRET');

        if (! $secret) {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        return StripeWebhook::constructEvent($payload, $sigHeader, $secret);
    }
}
