<?php

namespace Tests\Feature\Invoice;

use App\Enums\MonthlyInvoiceStatus;
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

class ClientInvoiceStripeCheckoutTest extends TestCase
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

    public function test_unauthenticated_checkout_request_is_rejected(): void
    {
        $this->postJson('/api/v1/client/monthly-invoices/1/checkout')->assertUnauthorized();
    }

    public function test_client_can_create_checkout_session_for_own_finalized_invoice(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $invoice->status = MonthlyInvoiceStatus::FINALIZED;
        $invoice->issued_at = now();
        $invoice->save();

        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->postJson("/api/v1/client/monthly-invoices/{$invoice->id}/checkout")
            ->assertOk();

        $response->assertJsonPath('data.invoice_id', $invoice->id);
        $response->assertJsonPath('data.status', 'finalized');
        $this->assertNotNull($response->json('data.checkout_url'));
        $this->assertNotNull($response->json('data.session_id'));

        $freshInvoice = $invoice->fresh();
        $this->assertNotNull($freshInvoice->stripe_checkout_session_id);
    }

    public function test_client_cannot_checkout_another_companys_invoice(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $invoiceB = $this->createGeneratedInvoiceForCompany($companyB);
        $invoiceB->status = MonthlyInvoiceStatus::FINALIZED;
        $invoiceB->save();

        $clientA = $this->makeClient($companyA);

        $this->actingAs($clientA, 'api')
            ->postJson("/api/v1/client/monthly-invoices/{$invoiceB->id}/checkout")
            ->assertNotFound();
    }

    public function test_client_cannot_checkout_draft_invoice(): void
    {
        $company = Company::factory()->create();
        $draftInvoice = $this->createGeneratedInvoiceForCompany($company);
        $this->assertSame(MonthlyInvoiceStatus::DRAFT, $draftInvoice->status);

        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson("/api/v1/client/monthly-invoices/{$draftInvoice->id}/checkout")
            ->assertStatus(422);
    }

    public function test_client_cannot_checkout_void_invoice(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $invoice->status = MonthlyInvoiceStatus::VOID;
        $invoice->save();

        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson("/api/v1/client/monthly-invoices/{$invoice->id}/checkout")
            ->assertStatus(409);
    }

    public function test_client_cannot_checkout_already_paid_invoice(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $invoice->status = MonthlyInvoiceStatus::FINALIZED;
        $invoice->save();

        $invoice->status = MonthlyInvoiceStatus::PAID;
        $invoice->paid_at = now();
        $invoice->save();

        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson("/api/v1/client/monthly-invoices/{$invoice->id}/checkout")
            ->assertStatus(409);
    }

    public function test_non_client_roles_are_forbidden_on_checkout(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $invoice->status = MonthlyInvoiceStatus::FINALIZED;
        $invoice->save();

        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson("/api/v1/client/monthly-invoices/{$invoice->id}/checkout")
            ->assertForbidden();
    }

    public function test_payment_status_endpoint_returns_authoritative_state(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->createGeneratedInvoiceForCompany($company);
        $invoice->status = MonthlyInvoiceStatus::FINALIZED;
        $invoice->save();

        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->getJson("/api/v1/client/monthly-invoices/{$invoice->id}/payment-status")
            ->assertOk();

        $response->assertJsonPath('data.invoice_id', $invoice->id);
        $response->assertJsonPath('data.status', 'finalized');
        $response->assertJsonPath('data.payment_pending', false);
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
