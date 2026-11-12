<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Company;
use App\Models\Role;
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
use Tests\TestCase;

/**
 * C2.9-B2.2 review transition matrix for ops Shipment Request endpoints.
 */
class DispatcherShipmentRequestReviewTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
    }

    public function test_start_review_moves_submitted_to_under_review(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);
        $historyBefore = $this->historyCount($order);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'under_review');

        $order->refresh();
        $this->assertSame(ShipmentOrderStatus::UNDER_REVIEW, $order->status->name);
        $this->assertSame($historyBefore + 1, $this->historyCount($order));

        $latest = $this->latestHistory($order);
        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $latest->fromStatus->name);
        $this->assertSame(ShipmentOrderStatus::UNDER_REVIEW, $latest->toStatus->name);
        $this->assertSame($dispatcher->id, $latest->changed_by_user_id);
        $this->assertNull($latest->reason);
    }

    public function test_start_review_rejects_invalid_state(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::DRAFT);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertStatus(422);

        $this->assertSame(ShipmentOrderStatus::DRAFT, $order->fresh()->status->name);
    }

    public function test_request_changes_requires_reason(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/request-changes', [])
            ->assertStatus(422);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/request-changes', ['reason' => '  '])
            ->assertStatus(422);
    }

    public function test_request_changes_persists_reason_and_history(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/request-changes', [
                'reason' => '  Need cargo weight  ',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.name', 'changes_requested');

        $latest = $this->latestHistory($order->fresh());
        $this->assertSame('Need cargo weight', $latest->reason);
        $this->assertSame($dispatcher->id, $latest->changed_by_user_id);
    }

    public function test_approve_moves_under_review_to_approved_with_optional_reason(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/approve', [
                'reason' => 'All requirements met',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.name', 'approved');

        $latest = $this->latestHistory($order->fresh());
        $this->assertSame('All requirements met', $latest->reason);
    }

    public function test_approve_without_reason_succeeds(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'approved');

        $this->assertNull($this->latestHistory($order->fresh())->reason);
    }

    public function test_reject_requires_reason_and_is_terminal(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/reject', [])
            ->assertStatus(422);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/reject', [
                'reason' => 'Outside service area',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.name', 'rejected');

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/approve')
            ->assertStatus(422);
    }

    public function test_invalid_states_for_review_actions_return_422(): void
    {
        $dispatcher = $this->makeDispatcher();
        $submitted = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$submitted->id.'/request-changes', ['reason' => 'Too early'])
            ->assertStatus(422);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$submitted->id.'/approve')
            ->assertStatus(422);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$submitted->id.'/reject', ['reason' => 'Too early'])
            ->assertStatus(422);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);

        $this->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertUnauthorized();
    }

    public function test_client_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED, $company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertForbidden();
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertForbidden();
    }

    public function test_admin_cannot_start_review(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review')
            ->assertForbidden();

        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $order->fresh()->status->name);
    }

    public function test_unknown_order_returns_404(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.Str::uuid()->toString().'/start-review')
            ->assertNotFound();
    }

    public function test_prohibited_lifecycle_fields_return_422(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/approve', [
                'status' => 'converted',
            ])
            ->assertStatus(422);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/request-changes', [
                'reason' => 'Valid reason here',
                'changed_by_user_id' => 99999,
            ])
            ->assertStatus(422);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review', [
                'reason' => 'Should not be accepted',
            ])
            ->assertStatus(422);
    }

    public function test_concurrent_start_review_allows_only_one_success(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::SUBMITTED);
        $historyBefore = $this->historyCount($order);

        $responseA = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review');

        $responseB = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/start-review');

        $statusCodes = collect([$responseA->status(), $responseB->status()])->sort()->values()->all();
        $this->assertSame([200, 422], $statusCodes);
        $this->assertSame($historyBefore + 1, $this->historyCount($order->fresh()));
    }

    public function test_concurrent_approve_and_reject_allow_only_one_success(): void
    {
        $dispatcher = $this->makeDispatcher();
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW);
        $historyBefore = $this->historyCount($order);

        $approve = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/approve');

        $reject = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipment-requests/'.$order->id.'/reject', [
                'reason' => 'Concurrent reject attempt',
            ]);

        $successCount = collect([$approve->status(), $reject->status()])->filter(fn ($code) => $code === 200)->count();
        $this->assertSame(1, $successCount);
        $this->assertSame($historyBefore + 1, $this->historyCount($order->fresh()));
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
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

    private function makeOrderInStatus(string $statusName, ?Company $company = null): ShipmentOrder
    {
        $company ??= Company::factory()->create();
        $status = Status::where('name', $statusName)->where('type', ShipmentOrderStatus::TYPE)->firstOrFail();

        return ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $status->id,
            'cargo_type' => 'General Cargo',
            'cargo_description' => 'Review transition test',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ]);
    }

    private function historyCount(ShipmentOrder $order): int
    {
        return ShipmentOrderStatusHistory::query()->where('shipment_order_id', $order->id)->count();
    }

    private function latestHistory(ShipmentOrder $order): ShipmentOrderStatusHistory
    {
        return ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->with(['fromStatus', 'toStatus'])
            ->orderByDesc('id')
            ->firstOrFail();
    }
}
