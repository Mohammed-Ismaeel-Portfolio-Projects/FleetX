<?php

namespace Tests\Feature\Invoice;

use App\Actions\Invoice\GenerateMonthlyInvoicesAction;
use App\Enums\InvoiceLifecycleErrorCode;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\Role;
use App\Models\User;
use App\Services\Invoice\InvoiceEligibilityService;
use App\Services\Invoice\MonthlyInvoiceGenerationService;
use App\Services\Invoice\MonthlyInvoiceLifecycleService;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\ShipmentFinalPricingService;
use App\Services\Pricing\ShipmentOrderPricingService;
use Carbon\Carbon;
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
 * Phase 6e — Monthly invoice lifecycle (finalize / void).
 */
class MonthlyInvoiceLifecycleTest extends TestCase
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

        Carbon::setTestNow('2026-08-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_draft_invoice_finalizes_successfully(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.issued_at', '2026-08-19 12:00:00');

        $this->assertSame(MonthlyInvoiceStatus::FINALIZED, $invoice->fresh()->status);
    }

    public function test_finalization_preserves_persisted_amounts_and_line_items(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $lineItem = $invoice->lineItems->first();

        $beforeInvoice = $invoice->fresh()->getAttributes();
        $beforeLineItem = $lineItem->fresh()->getAttributes();
        $beforeFinalPrice = $lineItem->finalPriceBreakdown->fresh()->getAttributes();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();

        $afterInvoice = $invoice->fresh()->getAttributes();
        $afterLineItem = $lineItem->fresh()->getAttributes();
        $afterFinalPrice = $lineItem->finalPriceBreakdown->fresh()->getAttributes();

        $this->assertSame($beforeInvoice['subtotal'], $afterInvoice['subtotal']);
        $this->assertSame($beforeInvoice['total'], $afterInvoice['total']);
        $this->assertSame($beforeInvoice['currency'], $afterInvoice['currency']);
        $this->assertSame($beforeLineItem, $afterLineItem);
        $this->assertSame($beforeFinalPrice, $afterFinalPrice);
    }

    public function test_finalization_does_not_invoke_pricing_services(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $this->mockPricingAndGenerationServicesShouldNotRun();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();
    }

    public function test_cannot_finalize_invoice_without_line_items(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $invoice->lineItems()->delete();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVOICE_HAS_NO_LINE_ITEMS->value);

        $this->assertSame(MonthlyInvoiceStatus::DRAFT, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->issued_at);
    }

    public function test_cannot_finalize_invoice_with_missing_subtotal(): void
    {
        $invoice = $this->createInvalidDraftInvoice(['subtotal' => '-1.00']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVOICE_MISSING_SUBTOTAL->value);
    }

    public function test_cannot_finalize_invoice_with_missing_total(): void
    {
        $invoice = $this->createInvalidDraftInvoice(['total' => '-1.00']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVOICE_MISSING_TOTAL->value);
    }

    public function test_cannot_finalize_invoice_with_missing_currency(): void
    {
        $invoice = $this->createInvalidDraftInvoice(['currency' => '']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVOICE_MISSING_CURRENCY->value);
    }

    public function test_cannot_finalize_invoice_with_invalid_line_item_state(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        DB::table('invoice_line_items')
            ->where('monthly_invoice_id', $invoice->id)
            ->update(['quantity' => 0]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVOICE_INVALID_LINE_ITEM_STATE->value);
    }

    public function test_cannot_finalize_invoice_with_currency_mismatch(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        DB::table('invoice_line_items')
            ->where('monthly_invoice_id', $invoice->id)
            ->update(['currency' => 'EUR']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVOICE_CURRENCY_MISMATCH->value);
    }

    public function test_void_invoice_cannot_be_finalized(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVALID_INVOICE_STATUS_TRANSITION->value)
            ->assertJsonPath('errors.current_status', 'void')
            ->assertJsonPath('errors.requested_status', 'finalized');
    }

    public function test_draft_invoice_can_be_voided(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.issued_at', null);

        $this->assertSame(MonthlyInvoiceStatus::VOID, $invoice->fresh()->status);
    }

    public function test_void_preserves_billing_amounts_and_issued_at(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $before = $invoice->fresh()->getAttributes();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk();

        $after = $invoice->fresh()->getAttributes();
        $this->assertSame($before['subtotal'], $after['subtotal']);
        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['currency'], $after['currency']);
        $this->assertNull($after['issued_at']);
    }

    public function test_finalized_invoice_can_be_voided_and_preserves_issued_at(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();

        $issuedAt = $invoice->fresh()->issued_at;
        $before = $invoice->fresh()->getAttributes();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.issued_at', '2026-08-19 12:00:00');

        $after = $invoice->fresh()->getAttributes();
        $this->assertSame(MonthlyInvoiceStatus::VOID, $invoice->fresh()->status);
        $this->assertTrue($issuedAt->equalTo($invoice->fresh()->issued_at));
        $this->assertSame($before['subtotal'], $after['subtotal']);
        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['currency'], $after['currency']);
    }

    public function test_repeated_void_is_rejected(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk();

        $afterFirst = $invoice->fresh()->getAttributes();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertStatus(409)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVOICE_ALREADY_VOID->value)
            ->assertJsonPath('errors.current_status', 'void')
            ->assertJsonPath('errors.requested_status', 'void');

        $this->assertSame($afterFirst, $invoice->fresh()->getAttributes());
    }

    public function test_repeated_finalization_is_idempotent_and_preserves_issued_at(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();

        $issuedAt = $invoice->fresh()->issued_at;
        $snapshot = $invoice->fresh()->getAttributes();

        Carbon::setTestNow('2026-08-20 15:00:00');

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.issued_at', '2026-08-19 12:00:00');

        $this->assertTrue($issuedAt->equalTo($invoice->fresh()->issued_at));
        $this->assertSame($snapshot['subtotal'], $invoice->fresh()->subtotal);
        $this->assertSame($snapshot['total'], $invoice->fresh()->total);
    }

    public function test_concurrent_finalization_produces_single_mutation(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $service = app(MonthlyInvoiceLifecycleService::class);

        $first = $service->finalize($invoice->id);
        $second = $service->finalize($invoice->id);

        $this->assertSame(MonthlyInvoiceStatus::FINALIZED, $first->status);
        $this->assertSame(MonthlyInvoiceStatus::FINALIZED, $second->status);
        $this->assertTrue($first->issued_at->equalTo($second->issued_at));
        $this->assertSame(1, MonthlyInvoice::where('status', MonthlyInvoiceStatus::FINALIZED)->count());
    }

    public function test_admin_can_finalize_and_void(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk()
            ->assertJsonPath('data.status', 'void');
    }

    public function test_dispatcher_cannot_finalize_or_void(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertForbidden();
    }

    public function test_client_cannot_finalize_or_void(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertForbidden();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertForbidden();
    }

    public function test_unauthenticated_lifecycle_requests_are_rejected(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $this->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertUnauthorized();

        $this->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertUnauthorized();
    }

    public function test_missing_invoice_returns_404(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/999999/finalize')
            ->assertNotFound();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/999999/void')
            ->assertNotFound();
    }

    public function test_request_cannot_override_server_controlled_billing_fields(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize', [
                'status' => 'finalized',
                'issued_at' => '2020-01-01 00:00:00',
                'total' => '0.01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'issued_at', 'total']);
    }

    public function test_ops_show_reflects_finalized_status(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.issued_at', '2026-08-19 12:00:00');
    }

    public function test_ops_show_reflects_void_status(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.issued_at', null);
    }

    public function test_client_show_reflects_finalized_status(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $client = $this->makeClient($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.issued_at', '2026-08-19 12:00:00');
    }

    public function test_client_show_reflects_void_status(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $client = $this->makeClient($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.issued_at', null);
    }

    public function test_list_endpoints_expose_finalized_status(): void
    {
        $company = Company::factory()->create();
        $finalized = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$finalized->id.'/finalize')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices?status=finalized')
            ->assertOk()
            ->assertJsonPath('data.data.0.status', 'finalized');
    }

    public function test_list_endpoints_expose_void_status(): void
    {
        $company = Company::factory()->create();
        $voidInvoice = $this->createGeneratedInvoiceForCompany($company);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$voidInvoice->id.'/void')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/monthly-invoices?status=void')
            ->assertOk()
            ->assertJsonFragment(['status' => 'void']);
    }

    public function test_generation_does_not_mutate_finalized_invoice(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $lineItem = $invoice->lineItems->first();
        [$periodStart, $periodEnd] = $this->billingPeriodContaining(
            $lineItem->finalPriceBreakdown->calculated_at,
        );

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();

        $before = $invoice->fresh()->getAttributes();

        app(GenerateMonthlyInvoicesAction::class)->execute(
            $company->id,
            $periodStart,
            $periodEnd,
        );

        $this->assertSame($before, $invoice->fresh()->getAttributes());
        $this->assertSame(1, $invoice->lineItems()->count());
    }

    public function test_pdf_export_works_for_draft_finalized_and_void_without_mutation(): void
    {
        $draft = $this->createGeneratedInvoiceForCompany(Company::factory()->create(), ['reference_number' => 'PDF-DRAFT']);
        $finalized = $this->createGeneratedInvoiceForCompany(Company::factory()->create(), ['reference_number' => 'PDF-FIN']);
        $void = $this->createGeneratedInvoiceForCompany(Company::factory()->create(), ['reference_number' => 'PDF-VOID']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$finalized->id.'/finalize')
            ->assertOk();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/monthly-invoices/'.$void->id.'/void')
            ->assertOk();

        foreach ([$draft, $finalized, $void] as $invoice) {
            $before = $invoice->fresh()->getAttributes();

            $response = $this->actingAs($admin, 'api')
                ->get('/api/v1/monthly-invoices/'.$invoice->id.'/pdf');

            $response->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $this->assertSame($before, $invoice->fresh()->getAttributes());
        }

        $factory = app(\App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory::class);
        $finalizedDoc = $factory->forOps($finalized->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown']));
        $voidDoc = $factory->forOps($void->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown']));

        $finalizedHtml = View::make('invoices.ops', ['document' => $finalizedDoc])->render();
        $voidHtml = View::make('invoices.ops', ['document' => $voidDoc])->render();

        $this->assertStringContainsString('finalized', $finalizedHtml);
        $this->assertStringContainsString('2026-08-19 12:00:00', $finalizedHtml);
        $this->assertStringContainsString('void', $voidHtml);
        $this->assertStringContainsString('VOID', $voidHtml);
        $this->assertStringNotContainsString('Issued At', $voidHtml);
    }

    public function test_cannot_finalize_with_non_finalized_final_price_snapshot(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $lineItem = $invoice->lineItems->first();

        DB::table('shipment_final_price_breakdowns')
            ->where('id', $lineItem->shipment_final_price_breakdown_id)
            ->update(['status' => 'pending']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', InvoiceLifecycleErrorCode::INVOICE_NOT_FINALIZABLE->value);
    }

    public function test_voided_invoice_shipment_remains_ineligible_for_new_invoice(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $shipmentId = $invoice->lineItems->first()->shipment_id;

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/void')
            ->assertOk();

        $shipment = \App\Models\Shipment::query()->findOrFail($shipmentId);
        $result = app(InvoiceEligibilityService::class)->evaluate($shipment);

        $this->assertFalse($result->isEligible());
        $this->assertSame('already_invoiced', $result->code->value);
    }

    public function test_finalized_invoice_cannot_have_billing_fields_mutated(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/v1/monthly-invoices/'.$invoice->id.'/finalize')
            ->assertOk();

        $invoice = $invoice->fresh();

        try {
            $invoice->update(['total' => '1.00']);
            $this->fail('Expected immutability exception was not thrown.');
        } catch (\App\Services\Invoice\Exceptions\MonthlyInvoiceLifecycleException $e) {
            $this->assertSame(InvoiceLifecycleErrorCode::INVOICE_IMMUTABLE, $e->errorCode);
        }
    }

    public function test_finalize_then_void_transition_is_consistent(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $service = app(MonthlyInvoiceLifecycleService::class);

        $finalized = $service->finalize($invoice->id);
        $voided = $service->void($invoice->id);

        $this->assertSame(MonthlyInvoiceStatus::VOID, $voided->status);
        $this->assertNotNull($finalized->issued_at);
        $this->assertTrue($finalized->issued_at->equalTo($voided->issued_at));
    }

    /**
     * @param  array<string, mixed>  $invoiceOverrides
     */
    private function createInvalidDraftInvoice(array $invoiceOverrides): MonthlyInvoice
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        DB::table('monthly_invoices')
            ->where('id', $invoice->id)
            ->update($invoiceOverrides);

        return $invoice->fresh(['lineItems']);
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
