<?php

namespace Tests\Feature\Invoice;

use App\Models\Company;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\Role;
use App\Models\User;
use App\Services\Invoice\Documents\InvoiceMoneyFormatter;
use App\Services\Invoice\InvoiceEligibilityService;
use App\Services\Invoice\MonthlyInvoiceGenerationService;
use App\Services\Invoice\MonthlyInvoicePdfService;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\ShipmentFinalPricingService;
use App\Services\Pricing\ShipmentOrderPricingService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Mockery;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\Support\CreatesInvoiceFixtures;
use Tests\Support\DefinesBillingPeriods;
use Tests\TestCase;

/**
 * Phase 6d — Invoice PDF export.
 */
class MonthlyInvoicePdfExportTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use CreatesInvoiceFixtures;
    use DefinesBillingPeriods;
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
    }

    public function test_ops_user_can_download_invoice_pdf_inline(): void
    {
        $company = Company::factory()->create(['company_name' => 'Acme Logistics Ltd']);
        $invoice = $this->createGeneratedInvoiceForCompany($company, [
            'reference_number' => 'PO-PDF-001',
        ]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')
            ->get('/api/v1/monthly-invoices/'.$invoice->id.'/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('invoice-'.$invoice->id.'.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertValidPdfBinary($response->getContent());
        $this->assertHtmlDocumentContains('invoices.ops', $invoice, [
            'FLeetX',
            'Invoice #'.$invoice->id,
            'Acme Logistics Ltd',
            'PO-PDF-001',
            InvoiceMoneyFormatter::format((string) $invoice->total, $invoice->currency),
        ]);
    }

    public function test_client_can_download_own_invoice_pdf_as_attachment(): void
    {
        $company = Company::factory()->create(['company_name' => 'Client Co']);
        $invoice = $this->createGeneratedInvoiceForCompany($company, [
            'reference_number' => 'CLIENT-PDF-001',
        ]);
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->get('/api/v1/client/monthly-invoices/'.$invoice->id.'/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertValidPdfBinary($response->getContent());
        $this->assertHtmlDocumentContains('invoices.client', $invoice, [
            'Client Co',
            'CLIENT-PDF-001',
            InvoiceMoneyFormatter::format((string) $invoice->total, $invoice->currency),
        ]);

        $opsHtml = View::make('invoices.ops', [
            'document' => app(\App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory::class)
                ->forOps($invoice->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown'])),
        ])->render();

        $this->assertStringContainsString('Operations Copy', $opsHtml);
        $this->assertStringNotContainsString('Operations Copy', View::make('invoices.client', [
            'document' => app(\App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory::class)
                ->forClient($invoice->fresh(['company', 'lineItems.shipment'])),
        ])->render());
    }

    public function test_client_cannot_download_foreign_invoice_pdf(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $foreignInvoice = $this->createGeneratedInvoiceForCompany($companyB);
        $client = $this->makeClient($companyA);

        $this->actingAs($client, 'api')
            ->get('/api/v1/client/monthly-invoices/'.$foreignInvoice->id.'/pdf')
            ->assertNotFound();
    }

    public function test_client_cannot_access_ops_pdf_endpoint(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->get('/api/v1/monthly-invoices/'.$invoice->id.'/pdf')
            ->assertForbidden();
    }

    public function test_unauthenticated_pdf_request_returns_401(): void
    {
        $invoice = $this->createGeneratedInvoiceForCompany(Company::factory()->create());

        $this->getJson('/api/v1/monthly-invoices/'.$invoice->id.'/pdf')->assertUnauthorized();
        $this->getJson('/api/v1/client/monthly-invoices/'.$invoice->id.'/pdf')->assertUnauthorized();
    }

    public function test_missing_invoice_returns_404(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->get('/api/v1/monthly-invoices/999999/pdf')
            ->assertNotFound();
    }

    public function test_pdf_uses_persisted_invoice_total_after_final_price_changes(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $lineItem = $invoice->lineItems->first();

        $invoice = $invoice->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown']);
        $expectedTotal = app(\App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory::class)
            ->forOps($invoice)
            ->totalFormatted;

        DB::table('shipment_final_price_breakdowns')
            ->where('id', $lineItem->shipment_final_price_breakdown_id)
            ->update(['final_total' => '1.00']);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')
            ->get('/api/v1/monthly-invoices/'.$invoice->id.'/pdf');

        $response->assertOk();
        $this->assertHtmlDocumentContains('invoices.ops', $invoice->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown']), [
            $expectedTotal,
        ]);

        $recalculatedSnapshotTotal = InvoiceMoneyFormatter::format('1.00', $invoice->currency);
        $html = View::make('invoices.ops', [
            'document' => app(\App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory::class)
                ->forOps($invoice->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown'])),
        ])->render();
        $this->assertStringNotContainsString($recalculatedSnapshotTotal, $html);
    }

    public function test_pdf_generation_does_not_invoke_pricing_or_generation_services(): void
    {
        $invoice = $this->createGeneratedInvoiceForCompany(Company::factory()->create());
        $admin = $this->makeAdmin();

        $this->mockPricingAndGenerationServicesShouldNotRun();

        $this->actingAs($admin, 'api')
            ->get('/api/v1/monthly-invoices/'.$invoice->id.'/pdf')
            ->assertOk();
    }

    public function test_pdf_generation_does_not_mutate_persisted_records(): void
    {
        $invoice = $this->createGeneratedInvoiceForCompany(Company::factory()->create());
        $lineItem = $invoice->lineItems->first();
        $finalPrice = $lineItem->finalPriceBreakdown;

        $before = [
            'invoice' => $invoice->fresh()->getAttributes(),
            'line_item' => $lineItem->fresh()->getAttributes(),
            'final_price' => $finalPrice->fresh()->getAttributes(),
        ];

        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->get('/api/v1/monthly-invoices/'.$invoice->id.'/pdf')
            ->assertOk();

        $this->assertSame($before['invoice'], $invoice->fresh()->getAttributes());
        $this->assertSame($before['line_item'], $lineItem->fresh()->getAttributes());
        $this->assertSame($before['final_price'], $finalPrice->fresh()->getAttributes());
    }

    public function test_null_issued_at_is_not_shown_in_rendered_html(): void
    {
        $invoice = $this->createGeneratedInvoiceForCompany(Company::factory()->create());
        $document = app(MonthlyInvoicePdfService::class)->loadForOps($invoice->id);
        $viewModel = app(\App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory::class)->forClient($document);

        $html = View::make('invoices.client', ['document' => $viewModel])->render();

        $this->assertStringNotContainsString('Issued At', $html);
    }

    public function test_multiple_line_items_and_long_company_name_render(): void
    {
        $company = Company::factory()->create([
            'company_name' => str_repeat('Global Enterprise Transport Holdings ', 4),
        ]);
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        foreach (range(1, 8) as $index) {
            $otherFixture = $this->createFinalizableShipment($this->createFullPricingPolicy(), [], [
                'customer_company_id' => $company->id,
                'reference_number' => 'BULK-REF-'.$index,
            ]);
            $finalPrice = $this->finalizeShipmentFixture($otherFixture);

            InvoiceLineItem::create([
                'monthly_invoice_id' => $invoice->id,
                'shipment_id' => $otherFixture['shipment']->id,
                'shipment_final_price_breakdown_id' => $finalPrice->id,
                'description' => str_repeat('Extended freight service description '.$index.' ', 6),
                'quantity' => 1,
                'unit_price' => 1000 + $index,
                'line_total' => 1000 + $index,
                'currency' => 'USD',
            ]);
        }

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')
            ->get('/api/v1/monthly-invoices/'.$invoice->id.'/pdf');

        $response->assertOk();
        $this->assertValidPdfBinary($response->getContent());
        $this->assertHtmlDocumentContains('invoices.ops', $invoice->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown']), [
            'BULK-REF-1',
            'Extended freight service description',
            'Global Enterprise Transport Holdings',
        ]);
    }

    private function assertValidPdfBinary(string $pdfBinary): void
    {
        $this->assertStringStartsWith('%PDF', $pdfBinary);
        $this->assertGreaterThan(1000, strlen($pdfBinary));
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertHtmlDocumentContains(string $view, MonthlyInvoice $invoice, array $needles): void
    {
        $factory = app(\App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory::class);
        $document = $view === 'invoices.ops'
            ? $factory->forOps($invoice)
            : $factory->forClient($invoice);

        $html = View::make($view, ['document' => $document])->render();

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $html, 'Expected HTML to contain: '.$needle);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockPricingAndGenerationServicesShouldNotRun(): void
    {
        $pricingEngine = Mockery::mock(PricingEngine::class);
        $pricingEngine->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $pricingEngine);

        $finalPricing = Mockery::mock(ShipmentFinalPricingService::class);
        $finalPricing->shouldNotReceive('finalize');
        $this->app->instance(ShipmentFinalPricingService::class, $finalPricing);

        $orderPricing = Mockery::mock(ShipmentOrderPricingService::class);
        $orderPricing->shouldNotReceive('calculateAndPersistSnapshot');
        $this->app->instance(ShipmentOrderPricingService::class, $orderPricing);

        $policyResolver = Mockery::mock(PricingPolicyResolver::class);
        $policyResolver->shouldNotReceive('resolve');
        $this->app->instance(PricingPolicyResolver::class, $policyResolver);

        $eligibility = Mockery::mock(InvoiceEligibilityService::class);
        $eligibility->shouldNotReceive('evaluate');
        $eligibility->shouldNotReceive('eligibleShipmentsForCompany');
        $this->app->instance(InvoiceEligibilityService::class, $eligibility);

        $generation = Mockery::mock(MonthlyInvoiceGenerationService::class);
        $generation->shouldNotReceive('generate');
        $this->app->instance(MonthlyInvoiceGenerationService::class, $generation);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }
}
