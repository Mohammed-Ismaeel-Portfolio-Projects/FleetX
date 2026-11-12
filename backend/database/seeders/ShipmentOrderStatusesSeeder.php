<?php

namespace Database\Seeders;

use App\Models\Status;
use App\Support\ShipmentOrderStatus;
use Illuminate\Database\Seeder;

class ShipmentOrderStatusesSeeder extends Seeder
{
    /**
     * Seed shipment_order lifecycle statuses (C2.9-A2 canonical vocabulary).
     */
    public function run(): void
    {
        foreach (ShipmentOrderStatus::all() as $name) {
            Status::updateOrCreate(
                ['name' => $name, 'type' => ShipmentOrderStatus::TYPE],
                ['name' => $name, 'type' => ShipmentOrderStatus::TYPE]
            );
        }
    }
}
