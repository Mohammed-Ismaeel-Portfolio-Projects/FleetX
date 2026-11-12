<?php

namespace Tests\Feature\Shipment;

use App\Models\Status;
use App\Support\ShipmentStatus;
use Database\Seeders\ShipmentStatusesSeeder;
use Database\Seeders\StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C4.2-A1 — Canonical shipment status vocabulary hygiene.
 */
class ShipmentStatusVocabularyHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_status_all_is_title_case_catalog_only(): void
    {
        $legacy = [
            'pending',
            'assigned',
            'in_transit',
            'at_customs',
            'delivered',
            'cancelled',
        ];

        foreach (ShipmentStatus::all() as $name) {
            $this->assertNotContains($name, $legacy);
            $this->assertSame($name, trim($name));
            $this->assertNotSame(strtolower($name), $name);
        }

        $this->assertSame('shipment', ShipmentStatus::TYPE);
        $this->assertContains(ShipmentStatus::IN_TRANSIT, ShipmentStatus::all());
        $this->assertContains(ShipmentStatus::WAITING_ASSIGNMENT, ShipmentStatus::all());
    }

    public function test_statuses_seeder_does_not_create_snake_case_shipment_statuses(): void
    {
        $this->seed(StatusesSeeder::class);

        $shipmentNames = Status::query()
            ->where('type', ShipmentStatus::TYPE)
            ->pluck('name')
            ->all();

        $this->assertSame([], $shipmentNames);

        $this->assertDatabaseHas('statuses', ['name' => 'Pending', 'type' => 'order']);
        $this->assertDatabaseHas('statuses', ['name' => 'Available', 'type' => 'driver']);
        $this->assertDatabaseHas('statuses', ['name' => 'Active', 'type' => 'assignment']);
    }

    public function test_canonical_seeder_creates_only_shipment_status_vocabulary(): void
    {
        $this->seed(StatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $shipmentNames = Status::query()
            ->where('type', ShipmentStatus::TYPE)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $expected = ShipmentStatus::all();
        sort($expected);
        $actual = $shipmentNames;
        sort($actual);

        $this->assertSame($expected, $actual);

        foreach (['pending', 'assigned', 'in_transit', 'at_customs', 'delivered', 'cancelled'] as $legacy) {
            $this->assertDatabaseMissing('statuses', [
                'name' => $legacy,
                'type' => ShipmentStatus::TYPE,
            ]);
        }
    }
}
