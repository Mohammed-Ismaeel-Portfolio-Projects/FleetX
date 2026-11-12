<?php

namespace Tests\Feature\Invoice;

use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\MonthlyInvoice;
use App\Models\MonthlyInvoicePayment;
use App\Services\Invoice\StripePaymentService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Event as StripeEvent;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\Support\CreatesInvoiceFixtures;
use Tests\Support\DefinesBillingPeriods;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use CreatesInvoiceFixtures;
    use DefinesBillingPeriods;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
    }

    public function test_missing_signature_returns_400(): void
    {
        $this->postJson('/api/v1/webhooks/stripe', ['type' => 'checkout.session.completed'])
            ->assertStatus(400);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $this->mock(StripePaymentService::class, function (MockInterface $mock) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andThrow(new \UnexpectedValueException('Invalid signature'));
        });

        $this->postJson('/api/v1/webhooks/stripe', ['type' => 'checkout.session.completed'], [
            'Stripe-Signature' => 't=12345,v1=invalid',
        ])->assertStatus(400);
    }

    public function test_valid_checkout_session_completed_marks_invoice_paid(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $invoice->status = MonthlyInvoiceStatus::FINALIZED;
        $invoice->issued_at = now();
        $invoice->save();

        $sessionId = 'cs_test_session_123';
        $intentId = 'pi_test_intent_456';

        $this->mockStripeWebhookEvent([
            'id' => 'evt_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_intent' => $intentId,
                    'client_reference_id' => (string) $invoice->id,
                    'metadata' => [
                        'fleetx_monthly_invoice_id' => (string) $invoice->id,
                        'fleetx_company_id' => (string) $company->id,
                    ],
                ],
            ],
        ]);

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=12345,v1=valid_sig'])
            ->assertOk();

        $freshInvoice = $invoice->fresh();
        $this->assertSame(MonthlyInvoiceStatus::PAID, $freshInvoice->status);
        $this->assertNotNull($freshInvoice->paid_at);
        $this->assertSame($sessionId, $freshInvoice->stripe_checkout_session_id);
        $this->assertSame($intentId, $freshInvoice->stripe_payment_intent_id);

        // Verify audit record in monthly_invoice_payments
        $payment = MonthlyInvoicePayment::where('stripe_checkout_session_id', $sessionId)->first();
        $this->assertNotNull($payment);
        $this->assertSame($invoice->id, $payment->monthly_invoice_id);
        $this->assertSame($company->id, $payment->company_id);
        $this->assertSame('succeeded', $payment->status);
        $this->assertSame($intentId, $payment->stripe_payment_intent_id);
    }

    public function test_duplicate_webhook_delivery_is_idempotent(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $invoice->status = MonthlyInvoiceStatus::FINALIZED;
        $invoice->issued_at = now();
        $invoice->save();

        $sessionId = 'cs_test_session_dedup';
        $intentId = 'pi_test_intent_dedup';

        $payload = [
            'id' => 'evt_dedup',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_intent' => $intentId,
                    'client_reference_id' => (string) $invoice->id,
                    'metadata' => [
                        'fleetx_monthly_invoice_id' => (string) $invoice->id,
                        'fleetx_company_id' => (string) $company->id,
                    ],
                ],
            ],
        ];

        $this->mockStripeWebhookEvent($payload);

        // First delivery
        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=12345,v1=valid_sig'])->assertOk();

        // Second delivery (duplicate)
        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=12345,v1=valid_sig'])->assertOk();

        $this->assertSame(1, MonthlyInvoicePayment::where('stripe_checkout_session_id', $sessionId)->count());
        $this->assertSame(MonthlyInvoiceStatus::PAID, $invoice->fresh()->status);
    }

    public function test_webhook_with_company_mismatch_is_rejected(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $invoiceA = $this->createGeneratedInvoiceForCompany($companyA);
        $invoiceA->status = MonthlyInvoiceStatus::FINALIZED;
        $invoiceA->save();

        $this->mockStripeWebhookEvent([
            'id' => 'evt_mismatch',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_mismatch',
                    'payment_intent' => 'pi_test_mismatch',
                    'client_reference_id' => (string) $invoiceA->id,
                    'metadata' => [
                        'fleetx_monthly_invoice_id' => (string) $invoiceA->id,
                        'fleetx_company_id' => (string) $companyB->id, // Mismatched company ID
                    ],
                ],
            ],
        ]);

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 't=12345,v1=valid_sig'])
            ->assertStatus(422);

        $this->assertSame(MonthlyInvoiceStatus::FINALIZED, $invoiceA->fresh()->status);
    }

    private function mockStripeWebhookEvent(array $eventPayload): void
    {
        $event = StripeEvent::constructFrom($eventPayload);

        $this->mock(StripePaymentService::class, function (MockInterface $mock) use ($event) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andReturn($event);
        });
    }
}
