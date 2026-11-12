<?php

namespace Tests\Feature\Invoice;

use App\Models\Company;
use App\Models\MonthlyInvoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\Support\CreatesInvoiceFixtures;
use Tests\Support\DefinesBillingPeriods;
use Tests\TestCase;

/**
 * Phase 6c — Client monthly invoice read API.
 */
class ClientMonthlyInvoiceReadTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use CreatesInvoiceFixtures;
    use DefinesBillingPeriods;
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/client/monthly-invoices')->assertUnauthorized();
    }

    public function test_linked_client_can_list_own_company_invoices_only(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $ownInvoice = $this->createGeneratedInvoiceForCompany($companyA);
        $this->createGeneratedInvoiceForCompany($companyB);

        $client = $this->makeClient($companyA);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices')
            ->assertOk();

        $response->assertJsonPath('data.meta.total', 1);
        $response->assertJsonPath('data.data.0.id', $ownInvoice->id);
        $response->assertJsonMissingPath('data.data.0.company');
        $response->assertJsonMissingPath('data.data.0.line_items');
    }

    public function test_linked_client_can_show_own_invoice(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company, [
            'reference_number' => 'PO-CLIENT-001',
        ]);
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices/'.$invoice->id)
            ->assertOk();

        $response->assertJsonPath('data.id', $invoice->id);
        $response->assertJsonPath('data.currency', 'USD');
        $response->assertJsonPath('data.line_items.0.shipment_reference', 'PO-CLIENT-001');
        $response->assertJsonPath('data.line_items.0.description', 'Shipment PO-CLIENT-001');
        $response->assertJsonMissingPath('data.company');
        $response->assertJsonMissingPath('data.line_items.0.shipment_id');
        $response->assertJsonMissingPath('data.line_items.0.final_price_breakdown_id');
        $response->assertJsonMissingPath('data.line_items.0.final_price_breakdown');
    }

    public function test_client_cannot_access_another_companys_invoice(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $foreignInvoice = $this->createGeneratedInvoiceForCompany($companyB);
        $client = $this->makeClient($companyA);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices/'.$foreignInvoice->id)
            ->assertNotFound();
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices')
            ->assertForbidden();
    }

    public function test_admin_and_dispatcher_are_forbidden_on_client_endpoints(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);

        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/monthly-invoices')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/monthly-invoices/'.$invoice->id)
            ->assertForbidden();
    }

    public function test_company_scope_query_params_are_prohibited(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices?company_id='.$company->id)
            ->assertUnprocessable();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices?customer_company_id='.$company->id)
            ->assertUnprocessable();
    }

    public function test_client_list_supports_filters_and_pagination(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $client = $this->makeClient($company);

        $query = http_build_query([
            'billing_period_start' => $invoice->billing_period_start->format('Y-m-d'),
            'billing_period_end' => $invoice->billing_period_end->format('Y-m-d'),
            'currency' => 'USD',
            'status' => 'draft',
            'per_page' => 10,
            'page' => 1,
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices?'.$query)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $invoice->id);
    }

    public function test_empty_client_list_returns_paginated_empty_result(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonCount(0, 'data.data');
    }

    public function test_get_does_not_modify_invoice_records(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $before = $invoice->fresh()->getAttributes();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/monthly-invoices/'.$invoice->id)
            ->assertOk();

        $this->assertSame($before, $invoice->fresh()->getAttributes());
        $this->assertSame(1, MonthlyInvoice::count());
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
