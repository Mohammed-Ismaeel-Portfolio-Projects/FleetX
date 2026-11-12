<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\Assignment\CreateAssignmentAction;
use App\Actions\ShipmentOrder\ConvertShipmentOrderToShipmentAction;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StatusesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestShipmentOperationalHandoffTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcher;
    private Status $statusSubmitted;
    private Status $statusUnderReview;
    private Status $statusApproved;
    private Status $statusConverted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
        $this->seed(StatusesSeeder::class);

        $dispatcherRole = Role::firstOrCreate(['name' => 'dispatcher']);
        $this->dispatcher = User::factory()->create(['role_id' => $dispatcherRole->id]);

        $this->statusSubmitted = Status::where('type', ShipmentOrderStatus::TYPE)->whereRaw('LOWER(name) = ?', [ShipmentOrderStatus::SUBMITTED])->firstOrFail();
        $this->statusUnderReview = Status::where('type', ShipmentOrderStatus::TYPE)->whereRaw('LOWER(name) = ?', [ShipmentOrderStatus::UNDER_REVIEW])->firstOrFail();
        $this->statusApproved = Status::where('type', ShipmentOrderStatus::TYPE)->whereRaw('LOWER(name) = ?', [ShipmentOrderStatus::APPROVED])->firstOrFail();
        $this->statusConverted = Status::where('type', ShipmentOrderStatus::TYPE)->whereRaw('LOWER(name) = ?', [ShipmentOrderStatus::CONVERTED])->firstOrFail();
    }

    public function test_default_request_query_returns_active_intake_and_excludes_converted_requests(): void
    {
        $company = Company::factory()->create();

        $submitted = ShipmentOrder::factory()->create(['customer_company_id' => $company->id, 'status_id' => $this->statusSubmitted->id]);
        $review = ShipmentOrder::factory()->create(['customer_company_id' => $company->id, 'status_id' => $this->statusUnderReview->id]);
        $approved = ShipmentOrder::factory()->create(['customer_company_id' => $company->id, 'status_id' => $this->statusApproved->id]);

        $shipment = Shipment::factory()->create();
        $converted = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusConverted->id,
            'converted_shipment_id' => $shipment->id,
        ]);

        $response = $this->actingAs($this->dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests');

        $response->assertOk();
        $items = $response->json('data.data');
        $ids = collect($items)->pluck('id')->all();

        $this->assertCount(3, $items);
        $this->assertContains($submitted->id, $ids);
        $this->assertContains($review->id, $ids);
        $this->assertContains($approved->id, $ids);
        $this->assertNotContains($converted->id, $ids);
    }

    public function test_converted_requests_can_be_retrieved_explicitly_via_status_or_exclude_converted_flag(): void
    {
        $company = Company::factory()->create();
        $shipment = Shipment::factory()->create();
        $converted = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusConverted->id,
            'converted_shipment_id' => $shipment->id,
        ]);

        // Explicit status filter
        $response1 = $this->actingAs($this->dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests?status=Converted');
        $response1->assertOk();
        $this->assertCount(1, $response1->json('data.data'));
        $this->assertEquals($converted->id, $response1->json('data.data.0.id'));

        // Explicit exclude_converted=false flag
        $response2 = $this->actingAs($this->dispatcher, 'api')
            ->getJson('/api/v1/shipment-requests?exclude_converted=false');
        $response2->assertOk();
        $ids = collect($response2->json('data.data'))->pluck('id')->all();
        $this->assertContains($converted->id, $ids);
    }

    public function test_conversion_preserves_original_request_and_links_shipment_and_assignment(): void
    {
        $company = Company::factory()->create();
        $order = ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusApproved->id,
            'converted_shipment_id' => null,
        ]);

        $convertAction = app(ConvertShipmentOrderToShipmentAction::class);
        $convertResult = $convertAction->execute($this->dispatcher, $order->id);

        $this->assertTrue($convertResult->success);

        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder->converted_shipment_id);
        $this->assertEquals($this->statusConverted->id, $freshOrder->status_id);

        /** @var Shipment $shipment */
        $shipment = Shipment::find($freshOrder->converted_shipment_id);
        $this->assertNotNull($shipment);
        $this->assertEquals($order->id, $shipment->shipment_order_id);

        // Assign shipment
        $asgStatus = Status::firstOrCreate(['name' => 'Assigned', 'type' => 'assignment']);
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $trailer = Trailer::factory()->create();

        $createAsgAction = app(CreateAssignmentAction::class);
        $asgResult = $createAsgAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-HANDOFF-001',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $asgStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($asgResult->success);

        // Verification of 3-tier relationship & data preservation
        $this->assertEquals($shipment->id, $asgResult->data->shipment_id);
        $this->assertEquals($order->id, $shipment->fresh()->shipment_order_id);
        $this->assertEquals($shipment->id, $freshOrder->fresh()->converted_shipment_id);
        $this->assertDatabaseHas('shipment_orders', ['id' => $order->id]);
    }
}
