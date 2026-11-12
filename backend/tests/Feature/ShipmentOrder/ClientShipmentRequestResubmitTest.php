<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\ShipmentOrder\TransitionShipmentOrderStatusAction;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderStatusHistory;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderPayloadSnapshotBuilder;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Feature\Pricing\Support\SetsUpPricingForShipmentOrders;
use Tests\TestCase;

/**
 * C2.9-A3.4.3c security / contract matrix for POST .../resubmit.
 */
class ClientShipmentRequestResubmitTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Status $statusChangesRequested;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();

        $this->statusChangesRequested = Status::where('name', ShipmentOrderStatus::CHANGES_REQUESTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->createFullPricingPolicy();
        $this->mockTruckRouteDistance(500.0);
    }

    public function test_linked_client_can_resubmit_changes_requested(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeResubmittableOrder($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.name', 'submitted')
            ->assertJsonPath('data.submission_revision', 2);
    }

    public function test_resubmit_persists_metadata_and_history(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $originalSubmitter = $this->makeClient($company);
        $resubmittingClient = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
        $order = $this->makeResubmittableOrder($company, [
            'submitted_by_user_id' => $originalSubmitter->id,
            'cargo_type' => 'general',
            'reference_number' => 'REF-RESUB-1',
        ]);

        $historyBefore = ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->count();

        $this->actingAs($resubmittingClient, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertOk();

        $order->refresh();
        $this->assertSame($resubmittingClient->id, $order->submitted_by_user_id);
        $this->assertSame(2, $order->submission_revision);
        $this->assertIsArray($order->payload_json);
        $this->assertSame(2, $order->payload_json['submission_revision']);
        $this->assertSame('general', $order->payload_json['cargo_type']);
        $this->assertSame('REF-RESUB-1', $order->payload_json['reference_number']);
        $this->assertArrayNotHasKey('customer_company_id', $order->payload_json);
        $this->assertArrayNotHasKey('submitted_by_user_id', $order->payload_json);

        $newHistory = ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->with(['fromStatus', 'toStatus'])
            ->orderByDesc('id')
            ->first();

        $this->assertSame(ShipmentOrderStatus::CHANGES_REQUESTED, $newHistory->fromStatus->name);
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $newHistory->toStatus->name);
        $this->assertSame($resubmittingClient->id, $newHistory->changed_by_user_id);
        $this->assertSame($historyBefore + 1, ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count());
    }

    public function test_dispatcher_reason_history_remains_intact(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
        $order = $this->makeResubmittableOrderWithLifecycleHistory($company, $client, $dispatcher);

        $dispatcherReasonRow = ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->whereHas('toStatus', fn ($q) => $q->where('name', ShipmentOrderStatus::CHANGES_REQUESTED))
            ->firstOrFail();

        $this->assertSame('Need weight', $dispatcherReasonRow->reason);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertOk();

        $dispatcherReasonRow->refresh();
        $this->assertSame('Need weight', $dispatcherReasonRow->reason);
    }

    public function test_response_excludes_private_fields(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeResubmittableOrder($company);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit');

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
        $order = $this->makeResubmittableOrder($company);
        $notificationsBefore = Notification::query()->count();
        $shipmentsBefore = Shipment::query()->count();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertOk();

        $this->assertSame($notificationsBefore, Notification::query()->count());
        $this->assertSame($shipmentsBefore, Shipment::query()->count());
        $this->assertNull($order->fresh()->converted_shipment_id);
    }

    public function test_incomplete_request_returns_422_and_does_not_mutate(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeResubmittableOrder($company, [
            'cargo_type' => null,
            'cargo_description' => null,
        ]);

        $revisionBefore = $order->submission_revision;
        $submitterBefore = $order->submitted_by_user_id;
        $payloadBefore = $order->payload_json;
        $historyBefore = ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertStatus(422);

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::CHANGES_REQUESTED, $order->status->name);
        $this->assertSame($revisionBefore, $order->submission_revision);
        $this->assertSame($submitterBefore, $order->submitted_by_user_id);
        $this->assertSame($payloadBefore, $order->payload_json);
        $this->assertSame($historyBefore, ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count());
    }

    public function test_prohibited_ownership_fields_return_422(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $other = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeResubmittableOrder($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit', [
                'customer_company_id' => $other->id,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit', [
                'company_id' => $other->id,
            ])
            ->assertStatus(422);
    }

    public function test_prohibited_lifecycle_fields_return_422(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeResubmittableOrder($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit', [
                'status' => 'approved',
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit', [
                'submission_revision' => 99,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit', [
                'payload_json' => ['injected' => true],
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit', [
                'submitted_by_user_id' => 99999,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit', [
                'reason' => 'Client override',
            ])
            ->assertStatus(422);
    }

    public function test_non_changes_requested_statuses_cannot_be_resubmitted(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);

        foreach ([
            ShipmentOrderStatus::DRAFT,
            ShipmentOrderStatus::SUBMITTED,
            ShipmentOrderStatus::UNDER_REVIEW,
            ShipmentOrderStatus::APPROVED,
            ShipmentOrderStatus::REJECTED,
            ShipmentOrderStatus::CANCELLED,
            ShipmentOrderStatus::CONVERTED,
        ] as $statusName) {
            $status = Status::where('name', $statusName)->where('type', ShipmentOrderStatus::TYPE)->firstOrFail();
            $order = $this->makeResubmittableOrder($company, ['status_id' => $status->id]);

            $this->actingAs($client, 'api')
                ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
                ->assertStatus(422);
        }
    }

    public function test_already_resubmitted_cannot_be_resubmitted_again_without_changes_requested(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeResubmittableOrder($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertOk();

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertStatus(422);

        $this->assertSame(2, $order->fresh()->submission_revision);
    }

    public function test_foreign_request_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeResubmittableOrder($companyB);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$foreign->id.'/resubmit')
            ->assertNotFound();
    }

    public function test_unknown_request_returns_404(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.Str::uuid()->toString().'/resubmit')
            ->assertNotFound();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $order = $this->makeResubmittableOrder($company);

        $this->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertUnauthorized();
    }

    public function test_admin_and_dispatcher_are_forbidden(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $order = $this->makeResubmittableOrder($company);

        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $dispatcher = User::factory()->create(['role_id' => $this->dispatcherRole->id, 'password' => Hash::make('password')]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertForbidden();
    }

    public function test_unlinked_client_returns_403(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $order = $this->makeResubmittableOrder($company);
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertForbidden();
    }

    public function test_concurrent_resubmit_allows_only_one_success(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $order = $this->makeResubmittableOrder($company);
        $historyBefore = ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count();

        $responseA = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit');

        $responseB = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit');

        $statusCodes = collect([$responseA->status(), $responseB->status()])->sort()->values()->all();
        $this->assertSame([200, 422], $statusCodes);

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $order->status->name);
        $this->assertSame(2, $order->submission_revision);
        $this->assertSame($historyBefore + 1, ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count());
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
    private function makeResubmittableOrder(Company $company, array $overrides = []): ShipmentOrder
    {
        $client = $this->makeClient($company);

        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusChangesRequested->id,
            'submission_revision' => 1,
            'submitted_by_user_id' => $client->id,
            'payload_json' => ShipmentOrderPayloadSnapshotBuilder::fromOrder(
                new ShipmentOrder(array_merge([
                    'cargo_description' => 'Office supplies shipment',
                    'pickup_city' => 'Warsaw',
                    'pickup_country' => 'PL',
                    'delivery_city' => 'Krakow',
                    'delivery_country' => 'PL',
                ], $this->pricingReadyOrderAttributes())),
                1
            ),
            'cargo_description' => 'Office supplies shipment',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $this->pricingReadyOrderAttributes(), $overrides));
    }

    private function makeResubmittableOrderWithLifecycleHistory(
        Company $company,
        User $client,
        User $dispatcher
    ): ShipmentOrder {
        $transition = app(TransitionShipmentOrderStatusAction::class);

        $order = ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => Status::where('name', ShipmentOrderStatus::DRAFT)->where('type', ShipmentOrderStatus::TYPE)->firstOrFail()->id,
            'submission_revision' => 0,
            'submitted_by_user_id' => null,
            'payload_json' => null,
            'cargo_description' => 'Office supplies shipment',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ], $this->pricingReadyOrderAttributes()));

        $order->update([
            'submitted_by_user_id' => $client->id,
            'submission_revision' => 1,
            'payload_json' => ShipmentOrderPayloadSnapshotBuilder::fromOrder($order, 1),
        ]);

        $order = $transition->execute($order->fresh(), ShipmentOrderStatus::SUBMITTED, $client->id);
        $order = $transition->execute($order, ShipmentOrderStatus::UNDER_REVIEW, $dispatcher->id);
        $order = $transition->execute($order, ShipmentOrderStatus::CHANGES_REQUESTED, $dispatcher->id, 'Need weight');

        return $order->fresh();
    }
}
