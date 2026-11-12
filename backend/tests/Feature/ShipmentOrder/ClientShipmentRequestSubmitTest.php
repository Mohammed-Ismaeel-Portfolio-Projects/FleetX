<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Company;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderStatusHistory;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

/**
 * C2.9-A3.4.3b security / contract matrix for POST .../submit.
 */
class ClientShipmentRequestSubmitTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Status $statusDraft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();

        $this->statusDraft = Status::where('name', ShipmentOrderStatus::DRAFT)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->createFullPricingPolicy();
        $this->mockTruckRouteDistance(500.0);
    }

    public function test_linked_client_can_submit_own_valid_draft(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.name', 'submitted')
            ->assertJsonPath('data.submission_revision', 1);
    }

    public function test_submission_persists_metadata_and_history(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company, [
            'cargo_type' => 'general',
            'reference_number' => 'REF-SUB-1',
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $order->refresh();
        $this->assertSame($client->id, $order->submitted_by_user_id);
        $this->assertSame(1, $order->submission_revision);
        $this->assertIsArray($order->payload_json);
        $this->assertSame(1, $order->payload_json['submission_revision']);
        $this->assertSame('general', $order->payload_json['cargo_type']);
        $this->assertSame('REF-SUB-1', $order->payload_json['reference_number']);
        $this->assertArrayNotHasKey('customer_company_id', $order->payload_json);
        $this->assertArrayNotHasKey('submitted_by_user_id', $order->payload_json);

        $history = ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->with(['fromStatus', 'toStatus'])
            ->get();

        $this->assertCount(1, $history);
        $this->assertSame(ShipmentOrderStatus::DRAFT, $history->first()->fromStatus->name);
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $history->first()->toStatus->name);
        $this->assertSame($client->id, $history->first()->changed_by_user_id);
    }

    public function test_response_excludes_private_fields(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayNotHasKey('payload_json', $data);
        $this->assertArrayNotHasKey('submitted_by_user_id', $data);
        $this->assertArrayNotHasKey('customer_company_id', $data);
        $this->assertArrayNotHasKey('converted_shipment_id', $data);
    }

    public function test_no_notification_or_shipment_is_created(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);
        $notificationsBefore = Notification::query()->count();
        $shipmentsBefore = Shipment::query()->count();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $this->assertSame($notificationsBefore, Notification::query()->count());
        $this->assertSame($shipmentsBefore, Shipment::query()->count());
        $this->assertNull($order->fresh()->converted_shipment_id);
    }

    public function test_incomplete_draft_returns_422_and_does_not_mutate(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusDraft->id,
            'submission_revision' => 0,
            'payload_json' => null,
            'submitted_by_user_id' => null,
            'cargo_type' => null,
            'cargo_description' => null,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422);

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->status->name);
        $this->assertSame(0, $order->submission_revision);
        $this->assertNull($order->payload_json);
        $this->assertNull($order->submitted_by_user_id);
        $this->assertSame(0, ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count());
    }

    public function test_submit_rejects_display_label_cargo_type(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company, [
            'cargo_type' => 'General Freight',
        ]);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422);

        $this->assertArrayHasKey('cargo_type', $response->json('errors'));
        $this->assertStringContainsString(
            'supported pricing cargo type',
            $response->json('errors.cargo_type.0')
        );

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
    }

    public function test_submit_rejects_missing_trailer_type(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company, [
            'trailer_type' => null,
        ]);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422);

        $this->assertArrayHasKey('trailer_type', $response->json('errors'));
        $this->assertStringContainsString(
            'trailer_type',
            $response->json('errors.trailer_type.0')
        );

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
    }

    public function test_customer_company_id_injection_returns_422(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $other = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'customer_company_id' => $other->id,
            ])
            ->assertStatus(422);
    }

    public function test_submitted_by_user_id_injection_returns_422(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'submitted_by_user_id' => 99999,
            ])
            ->assertStatus(422);
    }

    public function test_status_and_revision_injection_returns_422(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'status' => 'approved',
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'submission_revision' => 5,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'payload_json' => ['injected' => true],
            ])
            ->assertStatus(422);
    }

    public function test_already_submitted_cannot_be_submitted_again(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertStatus(422);

        $this->assertSame(
            1,
            ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count()
        );
    }

    public function test_non_draft_statuses_cannot_be_submitted(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);

        foreach ([
            ShipmentOrderStatus::UNDER_REVIEW,
            ShipmentOrderStatus::CHANGES_REQUESTED,
            ShipmentOrderStatus::APPROVED,
            ShipmentOrderStatus::REJECTED,
            ShipmentOrderStatus::CANCELLED,
            ShipmentOrderStatus::CONVERTED,
        ] as $statusName) {
            $status = Status::where('name', $statusName)->where('type', ShipmentOrderStatus::TYPE)->firstOrFail();
            $order = $this->makeSubmittableDraft($company, ['status_id' => $status->id]);

            $this->actingAs($client, 'api')
                ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
                ->assertStatus(422);
        }
    }

    public function test_foreign_request_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeSubmittableDraft($companyB);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$foreign->id.'/submit')
            ->assertNotFound();
    }

    public function test_unknown_request_returns_404(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.Str::uuid()->toString().'/submit')
            ->assertNotFound();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $order = $this->makeSubmittableDraft($company);

        $this->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertUnauthorized();
    }

    public function test_admin_and_dispatcher_are_forbidden(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $order = $this->makeSubmittableDraft($company);

        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $dispatcher = User::factory()->create(['role_id' => $this->dispatcherRole->id, 'password' => Hash::make('password')]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertForbidden();
    }

    public function test_unlinked_client_returns_403(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $order = $this->makeSubmittableDraft($company);
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertForbidden();
    }

    public function test_company_id_injection_returns_422(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $other = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'company_id' => $other->id,
            ])
            ->assertStatus(422);
    }

    public function test_converted_shipment_id_injection_returns_422(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit', [
                'converted_shipment_id' => Str::uuid()->toString(),
            ])
            ->assertStatus(422);
    }

    public function test_concurrent_submit_allows_only_one_success(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $responseA = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit');

        $responseB = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit');

        $statusCodes = collect([$responseA->status(), $responseB->status()])->sort()->values()->all();
        $this->assertSame([200, 422], $statusCodes);

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $order->status->name);
        $this->assertSame(1, $order->submission_revision);
        $this->assertSame(
            1,
            ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count()
        );
    }

    public function test_customer_company_id_unchanged_after_submit(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeSubmittableDraft($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/submit')
            ->assertOk();

        $this->assertSame($company->id, $order->fresh()->customer_company_id);
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSubmittableDraft(Company $company, array $overrides = []): ShipmentOrder
    {
        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusDraft->id,
            'submission_revision' => 0,
            'payload_json' => null,
            'submitted_by_user_id' => null,
            'cargo_description' => 'Office supplies shipment',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $this->pricingReadyOrderAttributes(), $overrides));
    }
}
