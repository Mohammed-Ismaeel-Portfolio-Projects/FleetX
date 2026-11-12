<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\ShipmentOrder\TransitionShipmentOrderStatusAction;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Role;
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
 * C2.9-A3.4.3a / C2.9-B2.3 security / contract matrix for PATCH /api/v1/client/shipment-requests/{shipmentOrder}.
 */
class ClientShipmentRequestUpdateTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpPricingForShipmentOrders;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Status $statusDraft;

    private Status $statusSubmitted;

    private Status $statusUnderReview;

    private Status $statusChangesRequested;

    private Status $statusApproved;

    private Status $statusRejected;

    private Status $statusCancelled;

    private Status $statusConverted;

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
        $this->statusSubmitted = Status::where('name', ShipmentOrderStatus::SUBMITTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusUnderReview = Status::where('name', ShipmentOrderStatus::UNDER_REVIEW)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusChangesRequested = Status::where('name', ShipmentOrderStatus::CHANGES_REQUESTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusApproved = Status::where('name', ShipmentOrderStatus::APPROVED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusRejected = Status::where('name', ShipmentOrderStatus::REJECTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusCancelled = Status::where('name', ShipmentOrderStatus::CANCELLED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusConverted = Status::where('name', ShipmentOrderStatus::CONVERTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->createFullPricingPolicy();
        $this->mockTruckRouteDistance(500.0);
    }

    public function test_linked_client_can_update_own_draft(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company, [
            'cargo_type' => 'general',
            'pickup_city' => 'Gdansk',
        ]);

        $response = $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
                'pickup_city' => 'Warsaw',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status.name', 'draft')
            ->assertJsonPath('data.cargo_type', 'heavy')
            ->assertJsonPath('data.pickup_city', 'Warsaw');
    }

    public function test_updated_fields_are_persisted_and_omitted_fields_remain_unchanged(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company, [
            'cargo_type' => 'Keep Me',
            'cargo_description' => 'Original description',
            'delivery_city' => 'Krakow',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_description' => 'Updated description',
            ])
            ->assertOk();

        $order->refresh();
        $this->assertSame('Keep Me', $order->cargo_type);
        $this->assertSame('Updated description', $order->cargo_description);
        $this->assertSame('Krakow', $order->delivery_city);
    }

    public function test_nullable_fields_can_be_cleared(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company, [
            'reference_number' => 'REF-OLD',
            'client_notes' => 'Some note',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'reference_number' => null,
                'client_notes' => null,
            ])
            ->assertOk();

        $order->refresh();
        $this->assertNull($order->reference_number);
        $this->assertNull($order->client_notes);
    }

    public function test_draft_state_fields_are_preserved(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'refrigerated',
            ])
            ->assertOk();

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->status->name);
        $this->assertSame(0, $order->submission_revision);
        $this->assertNull($order->payload_json);
        $this->assertNull($order->submitted_by_user_id);
    }

    public function test_no_status_history_row_is_created(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);
        $before = ShipmentOrderStatusHistory::query()->count();

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertOk();

        $this->assertSame($before, ShipmentOrderStatusHistory::query()->count());
        $this->assertSame(
            0,
            ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count()
        );
    }

    public function test_foreign_company_draft_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeDraft($companyB);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$foreign->id, [
                'cargo_type' => 'fragile',
            ])
            ->assertNotFound();

        $foreign->refresh();
        $this->assertNotSame('Hack', $foreign->cargo_type);
    }

    public function test_unknown_id_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.Str::uuid()->toString(), [
                'cargo_type' => 'fragile',
            ])
            ->assertNotFound();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeDraft($company);

        $this->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
            'cargo_type' => 'fragile',
        ])->assertUnauthorized();
    }

    public function test_admin_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeDraft($company);
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'fragile',
            ])
            ->assertForbidden();
    }

    public function test_dispatcher_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeDraft($company);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'fragile',
            ])
            ->assertForbidden();
    }

    public function test_unlinked_client_returns_403(): void
    {
        $company = Company::factory()->create();
        $order = $this->makeDraft($company);
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'fragile',
            ])
            ->assertForbidden();
    }

    public function test_customer_company_id_injection_returns_422(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'customer_company_id' => $other->id,
                'cargo_type' => 'fragile',
            ])
            ->assertStatus(422);
    }

    public function test_company_id_injection_returns_422(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'company_id' => $other->id,
                'cargo_type' => 'fragile',
            ])
            ->assertStatus(422);
    }

    public function test_status_injection_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'status' => 'approved',
            ])
            ->assertStatus(422);
    }

    public function test_submission_revision_injection_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'submission_revision' => 99,
            ])
            ->assertStatus(422);
    }

    public function test_payload_json_injection_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'payload_json' => ['injected' => true],
            ])
            ->assertStatus(422);
    }

    public function test_converted_shipment_id_injection_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'converted_shipment_id' => Str::uuid()->toString(),
            ])
            ->assertStatus(422);
    }

    public function test_dispatcher_notes_injection_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'dispatcher_notes' => 'internal',
            ])
            ->assertStatus(422);
    }

    public function test_priority_injection_returns_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'priority' => 'urgent',
            ])
            ->assertStatus(422);
    }

    public function test_operational_assignment_fields_are_rejected(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'driver_id' => 1,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'vehicle_id' => Str::uuid()->toString(),
            ])
            ->assertStatus(422);
    }

    public function test_response_excludes_server_owned_fields(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company, [
            'dispatcher_notes' => 'Must not leak',
            'payload_json' => ['secret' => true],
        ]);

        $response = $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'hazardous',
            ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayNotHasKey('customer_company_id', $data);
        $this->assertArrayNotHasKey('submitted_by_user_id', $data);
        $this->assertArrayNotHasKey('payload_json', $data);
        $this->assertArrayNotHasKey('converted_shipment_id', $data);
        $this->assertArrayNotHasKey('dispatcher_notes', $data);
    }

    public function test_no_notification_is_created(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeDraft($company);
        $before = Notification::query()->count();

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertOk();

        $this->assertSame($before, Notification::query()->count());
    }

    public function test_submitted_request_cannot_be_updated(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderWithStatus($company, $this->statusSubmitted, [
            'cargo_type' => 'Submitted Type',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertStatus(422);

        $order->refresh();
        $this->assertSame('Submitted Type', $order->cargo_type);
    }

    public function test_approved_request_cannot_be_updated(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderWithStatus($company, $this->statusApproved, [
            'cargo_type' => 'Approved Type',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertStatus(422);

        $order->refresh();
        $this->assertSame('Approved Type', $order->cargo_type);
    }

    public function test_converted_request_cannot_be_updated(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderWithStatus($company, $this->statusConverted, [
            'cargo_type' => 'Converted Type',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertStatus(422);

        $order->refresh();
        $this->assertSame('Converted Type', $order->cargo_type);
    }

    public function test_linked_client_can_update_changes_requested(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeChangesRequestedOrder($company, $client, [
            'cargo_type' => 'general',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'fragile',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.name', 'changes_requested')
            ->assertJsonPath('data.cargo_type', 'fragile');
    }

    public function test_changes_requested_state_fields_are_preserved(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $originalSubmitter = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
        $payload = ['submission_revision' => 1, 'cargo_type' => 'general'];
        $order = $this->makeChangesRequestedOrder($company, $client, [
            'submitted_by_user_id' => $originalSubmitter->id,
            'submission_revision' => 1,
            'payload_json' => $payload,
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_description' => 'Updated description',
            ])
            ->assertOk();

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::CHANGES_REQUESTED, $order->status->name);
        $this->assertSame(1, $order->submission_revision);
        $this->assertSame($payload, $order->payload_json);
        $this->assertSame($originalSubmitter->id, $order->submitted_by_user_id);
    }

    public function test_no_status_history_row_is_created_for_changes_requested_patch(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeChangesRequestedOrder($company, $client);
        $before = ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count();

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertOk();

        $this->assertSame(
            $before,
            ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count()
        );
    }

    public function test_foreign_company_changes_requested_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreignClient = $this->makeClient($companyB);
        $foreign = $this->makeChangesRequestedOrder($companyB, $foreignClient, [
            'cargo_type' => 'Foreign Type',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$foreign->id, [
                'cargo_type' => 'fragile',
            ])
            ->assertNotFound();

        $foreign->refresh();
        $this->assertSame('Foreign Type', $foreign->cargo_type);
    }

    public function test_under_review_request_cannot_be_updated(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderWithStatus($company, $this->statusUnderReview, [
            'cargo_type' => 'Under Review Type',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertStatus(422);

        $order->refresh();
        $this->assertSame('Under Review Type', $order->cargo_type);
    }

    public function test_rejected_request_cannot_be_updated(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderWithStatus($company, $this->statusRejected, [
            'cargo_type' => 'Rejected Type',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertStatus(422);

        $order->refresh();
        $this->assertSame('Rejected Type', $order->cargo_type);
    }

    public function test_cancelled_request_cannot_be_updated(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderWithStatus($company, $this->statusCancelled, [
            'cargo_type' => 'Cancelled Type',
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_type' => 'heavy',
            ])
            ->assertStatus(422);

        $order->refresh();
        $this->assertSame('Cancelled Type', $order->cargo_type);
    }

    public function test_prohibited_fields_return_422_while_changes_requested(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeChangesRequestedOrder($company, $client);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'status' => 'approved',
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'submission_revision' => 99,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'payload_json' => ['injected' => true],
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'customer_company_id' => $other->id,
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'dispatcher_notes' => 'internal',
            ])
            ->assertStatus(422);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'driver_id' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_patch_changes_requested_then_resubmit_integration(): void
    {
        $company = Company::factory()->create(['customer_type' => 'standard']);
        $client = $this->makeClient($company);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
        $order = $this->makeChangesRequestedOrderWithLifecycleHistory($company, $client, $dispatcher, [
            'cargo_type' => 'general',
            'cargo_description' => 'Needs correction',
        ]);

        $historyBefore = ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->count();

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/shipment-requests/'.$order->id, [
                'cargo_description' => 'Corrected office supplies shipment',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.name', 'changes_requested');

        $order->refresh();
        $this->assertSame('Corrected office supplies shipment', $order->cargo_description);
        $this->assertSame(1, $order->submission_revision);
        $this->assertSame($historyBefore, ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count());

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/shipment-requests/'.$order->id.'/resubmit')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'submitted')
            ->assertJsonPath('data.submission_revision', 2);

        $order->refresh();
        $this->assertSame(2, $order->submission_revision);
        $this->assertIsArray($order->payload_json);
        $this->assertSame(2, $order->payload_json['submission_revision']);
        $this->assertSame('Corrected office supplies shipment', $order->payload_json['cargo_description']);
        $this->assertSame('general', $order->payload_json['cargo_type']);

        $newHistory = ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->with(['fromStatus', 'toStatus'])
            ->orderByDesc('id')
            ->first();

        $this->assertSame(ShipmentOrderStatus::CHANGES_REQUESTED, $newHistory->fromStatus->name);
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $newHistory->toStatus->name);
        $this->assertSame($client->id, $newHistory->changed_by_user_id);
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
    private function makeDraft(Company $company, array $overrides = []): ShipmentOrder
    {
        return $this->makeOrderWithStatus($company, $this->statusDraft, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeChangesRequestedOrder(Company $company, User $client, array $overrides = []): ShipmentOrder
    {
        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusChangesRequested->id,
            'submission_revision' => 1,
            'submitted_by_user_id' => $client->id,
            'payload_json' => ShipmentOrderPayloadSnapshotBuilder::fromOrder(
                new ShipmentOrder([
                    'cargo_type' => 'general',
                    'cargo_description' => 'Office supplies shipment',
                    'pickup_city' => 'Warsaw',
                    'pickup_country' => 'PL',
                    'pickup_latitude' => 52.2297,
                    'pickup_longitude' => 21.0122,
                    'delivery_city' => 'Krakow',
                    'delivery_country' => 'PL',
                    'delivery_latitude' => 50.0647,
                    'delivery_longitude' => 19.9450,
                ]),
                1
            ),
            'cargo_type' => 'general',
            'cargo_description' => 'Office supplies shipment',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'pickup_latitude' => 52.2297,
            'pickup_longitude' => 21.0122,
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'delivery_latitude' => 50.0647,
            'delivery_longitude' => 19.9450,
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeChangesRequestedOrderWithLifecycleHistory(
        Company $company,
        User $client,
        User $dispatcher,
        array $overrides = []
    ): ShipmentOrder {
        $transition = app(TransitionShipmentOrderStatusAction::class);

        $order = ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusDraft->id,
            'submission_revision' => 0,
            'submitted_by_user_id' => null,
            'payload_json' => null,
            'cargo_description' => 'Office supplies shipment',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'requested_pickup_date' => now()->addDay()->toDateString(),
            'requested_delivery_date' => now()->addDays(3)->toDateString(),
        ], $this->pricingReadyOrderAttributes(), $overrides));

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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrderWithStatus(Company $company, Status $status, array $overrides = []): ShipmentOrder
    {
        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $status->id,
            'submission_revision' => $status->name === ShipmentOrderStatus::DRAFT ? 0 : 1,
            'payload_json' => $status->name === ShipmentOrderStatus::DRAFT ? null : ['revision' => 1],
            'submitted_by_user_id' => $status->name === ShipmentOrderStatus::DRAFT ? null : User::factory()->create()->id,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ], $overrides));
    }
}
