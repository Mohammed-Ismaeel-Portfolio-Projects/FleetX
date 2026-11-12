<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C2.9-B3.2 persistence prerequisites — reverse FK, relationships, canonical status.
 */
class ShipmentConversionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
    }

    public function test_shipment_can_have_null_shipment_order_id(): void
    {
        $shipment = Shipment::factory()->create(['shipment_order_id' => null]);

        $this->assertNull($shipment->shipment_order_id);
        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'shipment_order_id' => null,
        ]);
    }

    public function test_shipment_can_reference_valid_shipment_order(): void
    {
        $order = ShipmentOrder::factory()->draft()->create();
        $shipment = Shipment::factory()->create(['shipment_order_id' => $order->id]);

        $this->assertSame($order->id, $shipment->fresh()->shipment_order_id);
    }

    public function test_invalid_shipment_order_uuid_is_rejected_by_database(): void
    {
        $this->expectException(QueryException::class);

        Shipment::factory()->create([
            'shipment_order_id' => Str::uuid()->toString(),
        ]);
    }

    public function test_two_shipments_cannot_reference_same_shipment_order(): void
    {
        $order = ShipmentOrder::factory()->draft()->create();
        Shipment::factory()->create(['shipment_order_id' => $order->id]);

        $this->expectException(QueryException::class);
        Shipment::factory()->create(['shipment_order_id' => $order->id]);
    }

    public function test_existing_shipments_remain_valid_after_migration(): void
    {
        $shipment = Shipment::factory()->create();

        $this->assertNull($shipment->fresh()->shipment_order_id);
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id]);
    }

    public function test_deleting_shipment_order_is_restricted_when_shipment_references_it(): void
    {
        $order = ShipmentOrder::factory()->draft()->create();
        Shipment::factory()->create(['shipment_order_id' => $order->id]);

        $this->expectException(QueryException::class);
        $order->delete();
    }

    public function test_shipment_belongs_to_shipment_order_relationship(): void
    {
        $order = ShipmentOrder::factory()->draft()->create();
        $shipment = Shipment::factory()->create(['shipment_order_id' => $order->id]);

        $this->assertTrue($shipment->shipmentOrder->is($order));
    }

    public function test_shipment_order_has_one_linked_shipment_relationship(): void
    {
        $order = ShipmentOrder::factory()->draft()->create();
        $shipment = Shipment::factory()->create(['shipment_order_id' => $order->id]);

        $this->assertTrue($order->fresh()->linkedShipment->is($shipment));
    }

    public function test_waiting_assignment_exists_for_shipment_type(): void
    {
        $status = Status::where('name', ShipmentStatus::WAITING_ASSIGNMENT)
            ->where('type', ShipmentStatus::TYPE)
            ->first();

        $this->assertNotNull($status);
        $this->assertSame(ShipmentStatus::TYPE, $status->type);
    }

    public function test_conversion_initial_status_is_not_shipment_order_domain(): void
    {
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, ShipmentStatus::CONVERSION_INITIAL);

        $shipmentStatus = Status::where('name', ShipmentStatus::CONVERSION_INITIAL)
            ->where('type', ShipmentStatus::TYPE)
            ->firstOrFail();

        $orderStatus = Status::where('name', ShipmentOrderStatus::APPROVED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $this->assertNotSame($shipmentStatus->id, $orderStatus->id);
        $this->assertSame(ShipmentStatus::TYPE, $shipmentStatus->type);
        $this->assertSame(ShipmentOrderStatus::TYPE, $orderStatus->type);
    }

    public function test_conversion_initial_status_id_resolves(): void
    {
        $expected = Status::where('name', ShipmentStatus::WAITING_ASSIGNMENT)
            ->where('type', ShipmentStatus::TYPE)
            ->firstOrFail()
            ->id;

        $this->assertSame($expected, ShipmentStatus::conversionInitialStatusId());
    }

    public function test_shipment_statuses_seeder_is_idempotent(): void
    {
        $before = Status::where('type', ShipmentStatus::TYPE)->count();

        $this->seed(ShipmentStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $after = Status::where('type', ShipmentStatus::TYPE)->count();
        $waitingCount = Status::where('name', ShipmentStatus::WAITING_ASSIGNMENT)
            ->where('type', ShipmentStatus::TYPE)
            ->count();

        $this->assertSame($before, $after);
        $this->assertSame(1, $waitingCount);
    }

    public function test_direct_created_shipment_without_order_remains_valid(): void
    {
        $company = Company::factory()->create();
        $shipment = Shipment::factory()->forCustomer($company->id)->create([
            'shipment_order_id' => null,
        ]);

        $this->assertNull($shipment->shipment_order_id);
        $this->assertNull($shipment->shipmentOrder);
    }
}
