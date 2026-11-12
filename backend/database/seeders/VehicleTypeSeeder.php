<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Tractor Unit' => 'Heavy prime mover for semi-trailer and long-haul international freight.',
            'Box Truck' => 'Enclosed rigid truck for palletized freight and regional distribution.',
            'Flatbed Truck' => 'Open-deck vehicle for oversized cargo, steel, timber, and machinery.',
            'Refrigerated Truck' => 'Temperature-controlled truck for cold chain logistics.',
            'Container Chassis' => 'Chassis configured for ISO container movements.',
            'Tanker Truck' => 'Tank vehicle for liquid bulk and specialized freight.',
            'Heavy Haul Truck' => 'High-capacity unit for abnormal and overweight cargo.',
            'Curtainsider Truck' => 'Side-access freight body for flexible loading and unloading.',
            'Rigid Truck' => 'Single-frame commercial freight vehicle.',
            'Low Loader' => 'Low-bed heavy transport vehicle for tall or heavy equipment.',
        ];

        foreach ($types as $name => $description) {
            VehicleType::updateOrCreate(
                ['code' => Str::upper(Str::slug($name, '_'))],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }
    }
}
