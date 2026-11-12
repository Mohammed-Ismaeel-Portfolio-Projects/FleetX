<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentOrderPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShipmentOrderStatusesSeeder::class);
    }

    public function test_shipment_order_can_be_persisted(): void
    {
        $order = ShipmentOrder::factory()->create();

        $this->assertDatabaseHas('shipment_orders', [
            'id' => $order->id,
            'request_number' => $order->request_number,
        ]);
    }

    public function test_canonical_status_values_are_valid(): void
    {
        foreach (ShipmentOrderStatus::all() as $name) {
            $status = Status::where('name', $name)->where('type', ShipmentOrderStatus::TYPE)->first();
            $this->assertNotNull($status, "Missing status: {$name}");

            $order = ShipmentOrder::factory()->withStatus($name)->create();
            $this->assertSame($name, $order->status->name);
            $this->assertSame(ShipmentOrderStatus::TYPE, $order->status->type);
        }
    }

    public function test_customer_company_relationship_works(): void
    {
        $company = Company::factory()->create();
        $order = ShipmentOrder::factory()->forCustomer($company->id)->create();

        $this->assertTrue($order->customerCompany->is($company));
        $this->assertSame($company->id, $order->customer_company_id);
    }

    public function test_submitting_user_relationship_works(): void
    {
        $user = User::factory()->create();
        $order = ShipmentOrder::factory()->submittedBy($user)->create();

        $this->assertTrue($order->submittedBy->is($user));
        $this->assertSame($user->id, $order->submitted_by_user_id);
    }

    public function test_converted_shipment_relationship_is_nullable(): void
    {
        $order = ShipmentOrder::factory()->create(['converted_shipment_id' => null]);

        $this->assertNull($order->converted_shipment_id);
        $this->assertNull($order->convertedShipment);
    }

    public function test_converted_shipment_reference_is_unique(): void
    {
        $shipment = Shipment::factory()->create();
        ShipmentOrder::factory()->converted($shipment)->create();

        $this->expectException(QueryException::class);
        ShipmentOrder::factory()->converted($shipment)->create();
    }

    public function test_existing_shipment_records_remain_valid_without_order(): void
    {
        $shipment = Shipment::factory()->create();

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id]);
        $this->assertDatabaseMissing('shipment_orders', [
            'converted_shipment_id' => $shipment->id,
        ]);
    }

    public function test_shipment_order_does_not_require_shipment(): void
    {
        $order = ShipmentOrder::factory()->draft()->create([
            'converted_shipment_id' => null,
        ]);

        $this->assertNull($order->converted_shipment_id);
        $this->assertDatabaseHas('shipment_orders', ['id' => $order->id]);
    }

    public function test_shipment_order_status_is_independent_from_shipment_status(): void
    {
        $shipmentStatus = Status::create(['name' => 'In Transit', 'type' => 'shipment']);
        $orderStatus = Status::where('name', ShipmentOrderStatus::SUBMITTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $order = ShipmentOrder::factory()->create(['status_id' => $orderStatus->id]);
        $shipment = Shipment::factory()->create(['status_id' => $shipmentStatus->id]);

        $this->assertSame(ShipmentOrderStatus::SUBMITTED, $order->status->name);
        $this->assertSame(ShipmentOrderStatus::TYPE, $order->status->type);
        $this->assertSame('In Transit', $shipment->status->name);
        $this->assertSame('shipment', $shipment->status->type);
        $this->assertNotSame($order->status_id, $shipment->status_id);
    }

    public function test_request_number_unique_constraint(): void
    {
        ShipmentOrder::factory()->create(['request_number' => 'REQ-00001']);

        $this->expectException(QueryException::class);
        ShipmentOrder::factory()->create(['request_number' => 'REQ-00001']);
    }

    public function test_submission_revision_persists(): void
    {
        $order = ShipmentOrder::factory()->create(['submission_revision' => 3]);

        $this->assertSame(3, $order->fresh()->submission_revision);
    }

    public function test_payload_json_persists(): void
    {
        $payload = [
            'cargo_type' => 'General Cargo',
            'pickup_city' => 'Berlin',
            'delivery_city' => 'Munich',
        ];

        $order = ShipmentOrder::factory()->create(['payload_json' => $payload]);

        $fresh = $order->fresh();
        $this->assertSame($payload, $fresh->payload_json);
    }

    public function test_for_customer_scope_filters_by_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        ShipmentOrder::factory()->forCustomer($companyA->id)->count(2)->create();
        ShipmentOrder::factory()->forCustomer($companyB->id)->create();

        $scoped = ShipmentOrder::forCustomer($companyA->id)->get();
        $this->assertCount(2, $scoped);
        $scoped->each(fn ($o) => $this->assertSame($companyA->id, $o->customer_company_id));
    }

    public function test_no_fake_shipment_order_created_for_legacy_shipments(): void
    {
        $countBefore = ShipmentOrder::count();
        Shipment::factory()->count(3)->create();

        $this->assertSame($countBefore, ShipmentOrder::count());
    }
}
