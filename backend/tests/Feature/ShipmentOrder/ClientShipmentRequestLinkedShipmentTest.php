<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Company;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C2.9-C3.5 Client-safe Request → Shipment linked_shipment contract.
 */
class ClientShipmentRequestLinkedShipmentTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Status $statusSubmitted;

    private Status $statusConverted;

    private Status $waitingAssignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->statusSubmitted = Status::where('name', ShipmentOrderStatus::SUBMITTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->statusConverted = Status::where('name', ShipmentOrderStatus::CONVERTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();
        $this->waitingAssignment = Status::where('name', ShipmentStatus::WAITING_ASSIGNMENT)
            ->where('type', ShipmentStatus::TYPE)
            ->firstOrFail();
    }

    public function test_unconverted_show_returns_null_linked_shipment_without_internal_id_key(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('linked_shipment', $data);
        $this->assertNull($data['linked_shipment']);
        $this->assertArrayNotHasKey('converted_shipment_id', $data);
        $this->assertArrayNotHasKey('converted_shipment', $data);
        $this->assertArrayNotHasKey('dispatcher_notes', $data);
    }

    public function test_converted_show_returns_client_safe_linked_shipment(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        [$order, $shipment] = $this->makeConvertedOrder($company);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.status.name', 'converted')
            ->assertJsonPath('data.linked_shipment.id', $shipment->id)
            ->assertJsonPath('data.linked_shipment.shipment_number', $shipment->shipment_number)
            ->assertJsonPath('data.linked_shipment.status.name', ShipmentStatus::WAITING_ASSIGNMENT);

        $linked = $response->json('data.linked_shipment');
        $this->assertArrayNotHasKey('converted_shipment_id', $response->json('data'));
        $this->assertArrayNotHasKey('driver', $linked);
        $this->assertArrayNotHasKey('vehicle', $linked);
        $this->assertArrayNotHasKey('assignment', $linked);
        $this->assertArrayNotHasKey('gps', $linked);
        $this->assertArrayNotHasKey('eta', $linked);
        $this->assertArrayNotHasKey('shipment_order_id', $linked);
    }

    public function test_converted_list_includes_linked_shipment_for_own_company_only(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $client = $this->makeClient($company);
        [$ownOrder, $ownShipment] = $this->makeConvertedOrder($company);
        $this->makeConvertedOrder($other);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests')
            ->assertOk();

        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertSame($ownOrder->id, $rows[0]['id']);
        $this->assertSame($ownShipment->id, $rows[0]['linked_shipment']['id']);
        $this->assertArrayNotHasKey('converted_shipment_id', $rows[0]);
    }

    public function test_foreign_company_cannot_read_converted_request(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        [$order] = $this->makeConvertedOrder($company);
        $stranger = $this->makeClient($other);

        $this->actingAs($stranger, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertNotFound();
    }

    public function test_converted_without_shipment_row_returns_null_linked_shipment(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $order = $this->makeOrder($company, [
            'status_id' => $this->statusConverted->id,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipment-requests/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.status.name', 'converted');

        $this->assertNull($response->json('data.linked_shipment'));
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
    private function makeOrder(Company $company, array $overrides = []): ShipmentOrder
    {
        return ShipmentOrder::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusSubmitted->id,
            'submission_revision' => 1,
        ], $overrides));
    }

    /**
     * @return array{0: ShipmentOrder, 1: Shipment}
     */
    private function makeConvertedOrder(Company $company): array
    {
        $order = $this->makeOrder($company, [
            'status_id' => $this->statusConverted->id,
        ]);

        $shipment = Shipment::factory()->create([
            'customer_company_id' => $company->id,
            'shipment_order_id' => $order->id,
            'status_id' => $this->waitingAssignment->id,
            'shipment_number' => 'SHP-C35-'.substr($order->id, 0, 8),
        ]);

        $order->update(['converted_shipment_id' => $shipment->id]);

        return [$order->fresh(['status', 'convertedShipment.status']), $shipment->fresh(['status'])];
    }
}
