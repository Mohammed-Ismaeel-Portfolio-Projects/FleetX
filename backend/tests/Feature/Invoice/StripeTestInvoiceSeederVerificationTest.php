<?php

namespace Tests\Feature\Invoice;

use App\Enums\MonthlyInvoiceStatus;
use App\Models\MonthlyInvoice;
use Database\Seeders\StripeTestInvoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeTestInvoiceSeederVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_test_invoice_seeder_creates_valid_finalized_unpaid_invoice(): void
    {
        $this->seed(StripeTestInvoiceSeeder::class);

        $invoice = MonthlyInvoice::query()->first();

        $this->assertNotNull($invoice, 'Invoice must exist in database after seeding');
        $this->assertSame(MonthlyInvoiceStatus::FINALIZED, $invoice->status);
        $this->assertGreaterThan(0, (float) $invoice->total);
        $this->assertNotNull($invoice->company_id);
        $this->assertNull($invoice->paid_at);
        $this->assertNull($invoice->stripe_checkout_session_id);
        $this->assertNull($invoice->stripe_payment_intent_id);
        $this->assertSame(0, $invoice->payments()->count());

        // Test idempotency — running second time reuses same invoice
        $this->seed(StripeTestInvoiceSeeder::class);
        $this->assertSame(1, MonthlyInvoice::query()->count());
    }
}
