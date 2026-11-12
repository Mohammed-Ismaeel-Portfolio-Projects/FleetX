<?php

namespace Tests\Feature\Invoice;

use App\Models\Company;
use App\Models\MonthlyInvoice;
use App\Models\Role;
use App\Models\User;
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
use Mockery;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\Support\CreatesInvoiceFixtures;
use Tests\Support\DefinesBillingPeriods;
use Tests\TestCase;

/**
 * Phase 6c — Ops monthly invoice read API.
 */
class OpsMonthlyInvoiceReadTest extends TestCase
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

    public function test_unauthenticated_list_is_rejected(): void
    {
        $this->getJson('/api/v1/monthly-invoices')->assertUnauthorized();
    }

    public function test_dispatcher_can_list_invoices(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company, [
            'reference_number' => 'PO-OPS-001',
        ]);

        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/monthly-invoices')
            ->assertOk()
            ->assertJsonPath('success', true);

        $response->assertJsonPath('data.data.0.id', $invoice->id);
        $response->assertJsonPath('data.data.0.currency', 'USD');
        $response->assertJsonPath('data.meta.total', 1);
        $response->assertJsonMissingPath('data.data.0.line_items');
    }

    public function test_dispatcher_can_show_invoice_with_line_items(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company, [
            'reference_number' => 'PO-OPS-SHOW',
        ]);
        $lineItem = $invoice->lineItems->first();

        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/monthly-invoices/'.$invoice->id)
            ->assertOk();

        $response->assertJsonPath('data.id', $invoice->id);
        $response->assertJsonPath('data.company.id', $company->id);
        $response->assertJsonPath('data.billing_period.start', $invoice->billing_period_start->format('Y-m-d'));
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.issued_at', null);
        $response->assertJsonPath('data.line_items.0.id', $lineItem->id);
        $response->assertJsonPath('data.line_items.0.shipment_reference', 'PO-OPS-SHOW');
        $response->assertJsonPath('data.line_items.0.final_price_breakdown_id', $lineItem->shipment_final_price_breakdown_id);
        $response->assertJsonPath('data.line_items.0.quantity', 1);
        $response->assertJsonPath('data.line_items.0.final_price_breakdown.id', $lineItem->shipment_final_price_breakdown_id);
    }

    public function test_list_supports_company_filter(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $invoiceA = $this->createGeneratedInvoiceForCompany($companyA);
        $this->createGeneratedInvoiceForCompany($companyB);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices?company_id='.$companyA->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $invoiceA->id);
    }

    public function test_list_supports_period_currency_and_status_filters(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $query = http_build_query([
            'billing_period_start' => $invoice->billing_period_start->format('Y-m-d'),
            'billing_period_end' => $invoice->billing_period_end->format('Y-m-d'),
            'currency' => 'USD',
            'status' => 'draft',
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices?'.$query)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $invoice->id);
    }

    public function test_list_supports_pagination(): void
    {
        $company = Company::factory()->create();
        $this->createGeneratedInvoiceForCompany($company);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.current_page', 1);
    }

    public function test_client_cannot_access_ops_invoice_endpoints(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/monthly-invoices')
            ->assertForbidden();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/monthly-invoices/'.$invoice->id)
            ->assertForbidden();
    }

    public function test_get_does_not_invoke_pricing_services_or_mutate_records(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $lineItem = $invoice->lineItems->first();
        $finalPrice = $lineItem->finalPriceBreakdown;

        $before = [
            'invoice' => $invoice->fresh()->getAttributes(),
            'line_item' => $lineItem->fresh()->getAttributes(),
            'final_price' => $finalPrice->fresh()->getAttributes(),
        ];

        $pricingEngine = Mockery::mock(PricingEngine::class);
        $pricingEngine->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $pricingEngine);

        $finalPricing = Mockery::mock(ShipmentFinalPricingService::class);
        $finalPricing->shouldNotReceive('finalize');
        $this->app->instance(ShipmentFinalPricingService::class, $finalPricing);

        $policyResolver = Mockery::mock(PricingPolicyResolver::class);
        $policyResolver->shouldNotReceive('resolve');
        $this->app->instance(PricingPolicyResolver::class, $policyResolver);

        $orderPricing = Mockery::mock(ShipmentOrderPricingService::class);
        $orderPricing->shouldNotReceive('calculateAndPersistSnapshot');
        $this->app->instance(ShipmentOrderPricingService::class, $orderPricing);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices/'.$invoice->id)
            ->assertOk();

        $this->assertSame($before['invoice'], $invoice->fresh()->getAttributes());
        $this->assertSame($before['line_item'], $lineItem->fresh()->getAttributes());
        $this->assertSame($before['final_price'], $finalPrice->fresh()->getAttributes());
        $this->assertSame(1, MonthlyInvoice::count());
    }

    public function test_show_uses_persisted_invoice_amounts(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        DB::table('monthly_invoices')
            ->where('id', $invoice->id)
            ->update(['subtotal' => '999.99', 'total' => '999.99']);

        DB::table('shipment_final_price_breakdowns')
            ->where('id', $invoice->lineItems->first()->shipment_final_price_breakdown_id)
            ->update(['final_total' => '1.00']);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('data.total', 999.99)
            ->assertJsonPath('data.line_items.0.line_total', (float) $invoice->lineItems->first()->line_total);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
