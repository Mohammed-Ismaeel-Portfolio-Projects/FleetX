<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class VehicleStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'Available',
            'Assigned',
            'In Transit',
            'Loading',
            'Unloading',
            'At Customs',
            'Maintenance',
            'Out of Service',
            'Inactive',
        ];

        foreach ($statuses as $name) {
            Status::updateOrCreate(
                ['name' => $name, 'type' => 'vehicle'],
                ['name' => $name, 'type' => 'vehicle']
            );
        }
    }
}
