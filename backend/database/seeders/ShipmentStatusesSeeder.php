<?php

namespace Database\Seeders;

use App\Models\Status;
use App\Support\ShipmentStatus;
use Illuminate\Database\Seeder;

class ShipmentStatusesSeeder extends Seeder
{
    /**
     * Seed shipment operational statuses (C2.9-B3.2 canonical vocabulary).
     */
    public function run(): void
    {
        foreach (ShipmentStatus::all() as $name) {
            Status::updateOrCreate(
                ['name' => $name, 'type' => ShipmentStatus::TYPE],
                ['name' => $name, 'type' => ShipmentStatus::TYPE]
            );
        }
    }
}
