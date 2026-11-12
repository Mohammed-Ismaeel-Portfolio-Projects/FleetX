<?php

namespace Database\Seeders;

use App\Models\FuelType;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            VehicleTypeSeeder::class,
            FuelTypeSeeder::class,
            VehicleStatusSeeder::class,
        ]);

        Vehicle::withTrashed()->get()->each->forceDelete();
        fake()->unique(true);

        $availableStatusId = VehicleFactory::vehicleStatusId('Available');
        $inactiveStatusId = VehicleFactory::vehicleStatusId('Inactive');

        Vehicle::factory()
            ->count(120)
            ->active($availableStatusId)
            ->state($this->vehicleSequence(0))
            ->create();

        Vehicle::factory()
            ->count(30)
            ->inactive($inactiveStatusId)
            ->state($this->vehicleSequence(120))
            ->create();

        $this->command?->info('Successfully seeded 150 realistic heavy logistics vehicles.');
    }

    private function vehicleSequence(int $offset): Sequence
    {
        $vehicleTypeIds = VehicleType::query()->pluck('id')->values();
        $fuelTypeIds = FuelType::query()->pluck('id')->values();

        return new Sequence(function (Sequence $sequence) use ($offset, $vehicleTypeIds, $fuelTypeIds): array {
            $index = $offset + $sequence->index + 1;
            $routingClass = match ($index % 5) {
                0 => Vehicle::ROUTING_CLASS_VAN,
                1 => Vehicle::ROUTING_CLASS_LIGHT_TRUCK,
                default => Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
            };

            [$grossWeight, $height, $width, $length] = match ($routingClass) {
                Vehicle::ROUTING_CLASS_VAN => [3200, 2.60, 2.10, 5.80],
                Vehicle::ROUTING_CLASS_LIGHT_TRUCK => [7500, 3.00, 2.35, 8.20],
                default => [40000, 4.00, 2.55, 16.50],
            };

            // Slight per-vehicle variance while staying legal for EU freight.
            $height += (($index % 3) * 0.05);
            $width += (($index % 2) * 0.02);
            $length += (($index % 4) * 0.10);

            return [
                'vehicle_type_id' => $vehicleTypeIds->get(($index - 1) % max(1, $vehicleTypeIds->count())),
                'fuel_type_id' => $fuelTypeIds->get(($index - 1) % max(1, $fuelTypeIds->count())),
                'operational_code' => sprintf('VH-%05d', $index),
                'fleet_number' => sprintf('FX-%04d', $index),
                'plate_number' => sprintf('FX-%05d', $index),
                'vin' => sprintf('1FVFLEETX%08d', $index),
                'routing_class' => $routingClass,
                'gross_weight_kg' => $grossWeight,
                'payload_capacity_kg' => (int) max(800, $grossWeight - 5000),
                'height_m' => round($height, 2),
                'width_m' => round($width, 2),
                'length_m' => round($length, 2),
            ];
        });
    }
}
