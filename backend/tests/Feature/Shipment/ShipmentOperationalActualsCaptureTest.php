<?php

namespace Tests\Feature\Shipment;

use App\Enums\OperationalActualsCaptureSource;
use App\Enums\PricingPolicyStatus;
use App\Models\Company;
use App\Models\PricingPolicy;
use App\Models\PricingServiceCatalog;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOperationalActuals;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\Status;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use App\Services\Shipment\ShipmentOperationalActualsCaptureService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Phase 5.5a — Shipment operational actuals foundation.
 */
class ShipmentOperationalActualsCaptureTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $dispatcherRole;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
    }

    public function test_shipment_has_one_operational_actuals_relationship(): void
    {
        $shipment = $this->makeLinkedShipment();

        ShipmentOperationalActuals::create([
            'shipment_id' => $shipment->id,
            'actual_distance_km' => 500.0,
            'capture_source' => OperationalActualsCaptureSource::OPS_MANUAL,
            'captured_at' => now(),
        ]);

        $shipment->load('operationalActuals');

        $this->assertNotNull($shipment->operationalActuals);
        $this->assertSame($shipment->id, $shipment->operationalActuals->shipment_id);
    }

    public function test_dispatcher_can_capture_operational_actuals(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $response = $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 575.5,
                'actual_cargo_weight' => 18.25,
                'actual_loading_wait_minutes' => 45,
                'actual_unloading_wait_minutes' => 120,
                'actual_traffic_delay_minutes' => 0,
                'actual_customer_delay_minutes' => 15,
                'actual_border_crossing' => false,
                'actual_hazmat' => false,
                'actual_trailer_type' => 'dry_van',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shipment_id', $shipment->id)
            ->assertJsonPath('data.capture_source', 'ops_manual')
            ->assertJsonPath('data.actual_distance_km', 575.5)
            ->assertJsonPath('data.actual_loading_wait_minutes', 45)
            ->assertJsonPath('data.captured_by_user_id', $dispatcher->id);

        $this->assertDatabaseCount('shipment_operational_actuals', 1);
    }

    public function test_client_cannot_capture_operational_actuals(): void
    {
        $company = Company::factory()->create();
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
        $shipment = $this->makeLinkedShipment($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 100,
            ])
            ->assertForbidden();
    }

    public function test_negative_distance_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['actual_distance_km']);
    }

    public function test_negative_weight_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_cargo_weight' => -5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['actual_cargo_weight']);
    }

    public function test_negative_waiting_minutes_are_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_loading_wait_minutes' => -10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['actual_loading_wait_minutes']);
    }

    public function test_invalid_capture_source_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'invalid_source',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capture_source']);
    }

    public function test_invalid_trailer_type_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_trailer_type' => 'invalid_trailer',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['actual_trailer_type']);
    }

    public function test_invalid_service_ids_are_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_service_ids' => [999999],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['actual_service_ids.0']);
    }

    public function test_valid_service_ids_are_persisted(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();
        $service = $this->createCatalogService();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_service_ids' => [$service->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.actual_service_ids', [$service->id]);
    }

    public function test_server_controls_captured_at_and_captured_by_user_id(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 100,
                'captured_at' => '2020-01-01 00:00:00',
                'captured_by_user_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captured_at', 'captured_by_user_id']);
    }

    public function test_non_manual_capture_source_does_not_set_captured_by_user_id(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'gps_aggregation',
                'actual_distance_km' => 510,
            ])
            ->assertCreated()
            ->assertJsonPath('data.capture_source', 'gps_aggregation')
            ->assertJsonPath('data.captured_by_user_id', null);
    }

    public function test_second_capture_for_same_shipment_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 500,
            ])
            ->assertCreated();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 600,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'SHIPMENT_OPERATIONAL_ACTUALS_ALREADY_EXISTS');

        $this->assertSame(
            500.0,
            (float) ShipmentOperationalActuals::query()->where('shipment_id', $shipment->id)->value('actual_distance_km')
        );
    }

    public function test_cancelled_shipment_cannot_receive_operational_actuals(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();
        $cancelledStatus = Status::where('name', 'Cancelled')->where('type', 'shipment')->firstOrFail();
        $shipment->update(['status_id' => $cancelledStatus->id]);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'SHIPMENT_CANCELLED');
    }

    public function test_shipment_without_order_link_cannot_receive_operational_actuals(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = Shipment::factory()->waitingAssignment()->create([
            'shipment_order_id' => null,
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MISSING_SHIPMENT_ORDER');
    }

    public function test_missing_values_remain_null_and_explicit_zero_is_persisted(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_loading_wait_minutes' => 0,
            ])
            ->assertCreated();

        $record = ShipmentOperationalActuals::query()->where('shipment_id', $shipment->id)->firstOrFail();

        $this->assertNull($record->actual_distance_km);
        $this->assertSame(0, $record->actual_loading_wait_minutes);
        $this->assertNull($record->actual_unloading_wait_minutes);
    }

    public function test_capture_does_not_invoke_pricing_engine(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $engineMock = Mockery::mock(PricingEngine::class);
        $engineMock->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $engineMock);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 500,
            ])
            ->assertCreated();
    }

    public function test_capture_does_not_modify_submission_price_breakdown(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $shipment->shipment_order_id,
            'pricing_policy_id' => PricingPolicy::create([
                'name' => 'Test Policy',
                'status' => PricingPolicyStatus::ACTIVE,
                'currency' => 'USD',
                'tax_rate' => 8.0,
                'tax_inclusive' => false,
            ])->id,
            'breakdown_json' => ['finalTotal' => 1450.0],
            'final_total' => 1450.00,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $before = ShipmentOrderPriceBreakdown::query()->count();

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/shipments/'.$shipment->id.'/operational-actuals', [
                'capture_source' => 'ops_manual',
                'actual_distance_km' => 999,
            ])
            ->assertCreated();

        $this->assertSame($before, ShipmentOrderPriceBreakdown::query()->count());
        $this->assertSame(
            '1450.00',
            (string) ShipmentOrderPriceBreakdown::query()->first()->final_total
        );
    }

    public function test_service_capture_persists_via_application_service(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeLinkedShipment();

        $record = app(ShipmentOperationalActualsCaptureService::class)->capture(
            $shipment,
            $dispatcher,
            OperationalActualsCaptureSource::SIMULATION,
            [
                'actual_distance_km' => 123.456,
                'actual_loading_wait_minutes' => 30,
            ],
        );

        $this->assertSame('123.456', (string) $record->actual_distance_km);
        $this->assertSame(OperationalActualsCaptureSource::SIMULATION, $record->capture_source);
        $this->assertNull($record->captured_by_user_id);
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeLinkedShipment(?Company $company = null): Shipment
    {
        $company ??= Company::factory()->create();

        $order = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
        ]);

        return Shipment::factory()->waitingAssignment()->create([
            'customer_company_id' => $company->id,
            'shipment_order_id' => $order->id,
        ]);
    }

    private function createCatalogService(): PricingServiceCatalog
    {
        $policy = PricingPolicy::create([
            'name' => 'Catalog Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0,
            'tax_inclusive' => false,
        ]);

        return PricingServiceCatalog::create([
            'pricing_policy_id' => $policy->id,
            'name' => 'Liftgate Service',
            'price' => 150.00,
            'enabled' => true,
        ]);
    }
}
