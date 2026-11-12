<?php

namespace Tests\Unit\Invoice;

use App\Enums\InvoiceLifecycleErrorCode;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\MonthlyInvoice;
use App\Services\Invoice\Exceptions\MonthlyInvoiceLifecycleException;
use App\Services\Invoice\MonthlyInvoiceLifecycleService;
use Carbon\Carbon;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\Support\CreatesInvoiceFixtures;
use Tests\Support\DefinesBillingPeriods;
use Tests\TestCase;

/**
 * Phase 6e — Monthly invoice lifecycle service unit tests.
 */
class MonthlyInvoiceLifecycleServiceTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use CreatesInvoiceFixtures;
    use DefinesBillingPeriods;
    use RefreshDatabase;

    private MonthlyInvoiceLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->service = app(MonthlyInvoiceLifecycleService::class);
        Carbon::setTestNow('2026-08-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_finalize_sets_issued_at_from_server_clock(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $result = $this->service->finalize($invoice->id);

        $this->assertSame(MonthlyInvoiceStatus::FINALIZED, $result->status);
        $this->assertNotNull($result->issued_at);
        $this->assertSame('2026-08-19 12:00:00', $result->issued_at->format('Y-m-d H:i:s'));
    }

    public function test_void_from_draft_leaves_issued_at_null(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $result = $this->service->void($invoice->id);

        $this->assertSame(MonthlyInvoiceStatus::VOID, $result->status);
        $this->assertNull($result->issued_at);
    }

    public function test_void_from_finalized_preserves_issued_at(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $finalized = $this->service->finalize($invoice->id);
        $voided = $this->service->void($invoice->id);

        $this->assertSame(MonthlyInvoiceStatus::VOID, $voided->status);
        $this->assertTrue($finalized->issued_at->equalTo($voided->issued_at));
    }

    public function test_finalize_throws_for_missing_invoice(): void
    {
        try {
            $this->service->finalize(999999);
            $this->fail('Expected MonthlyInvoiceLifecycleException was not thrown.');
        } catch (MonthlyInvoiceLifecycleException $e) {
            $this->assertSame(404, $e->statusCode);
        }
    }

    public function test_repeated_finalize_preserves_issued_at_and_amounts(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $first = $this->service->finalize($invoice->id);

        Carbon::setTestNow('2026-08-20 15:00:00');

        $second = $this->service->finalize($invoice->id);

        $this->assertSame((string) $first->subtotal, (string) $second->subtotal);
        $this->assertSame((string) $first->total, (string) $second->total);
        $this->assertTrue($first->issued_at->equalTo($second->issued_at));
    }

    public function test_repeated_void_throws_invalid_transition(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $this->service->void($invoice->id);

        try {
            $this->service->void($invoice->id);
            $this->fail('Expected MonthlyInvoiceLifecycleException was not thrown.');
        } catch (MonthlyInvoiceLifecycleException $e) {
            $this->assertSame(409, $e->statusCode);
            $this->assertSame(InvoiceLifecycleErrorCode::INVOICE_ALREADY_VOID, $e->errorCode);
        }
    }

    public function test_cannot_finalize_with_empty_subtotal_string(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        DB::table('monthly_invoices')
            ->where('id', $invoice->id)
            ->update(['subtotal' => '-1.00']);

        try {
            $this->service->finalize($invoice->id);
            $this->fail('Expected MonthlyInvoiceLifecycleException was not thrown.');
        } catch (MonthlyInvoiceLifecycleException $e) {
            $this->assertSame(422, $e->statusCode);
            $this->assertSame(InvoiceLifecycleErrorCode::INVOICE_MISSING_SUBTOTAL, $e->errorCode);
        }
    }
}
