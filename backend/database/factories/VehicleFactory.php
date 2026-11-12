<?php

namespace Database\Factories;

use App\Models\FuelType;
use App\Models\Status;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    private const MANUFACTURERS = [
        'Volvo' => ['FH16', 'FH', 'FMX'],
        'Scania' => ['R 500', 'S 730', 'G 410'],
        'Mercedes-Benz' => ['Actros', 'Arocs', 'Atego'],
        'MAN' => ['TGX', 'TGS', 'TGM'],
        'DAF' => ['XF', 'XG+', 'CF'],
        'Iveco' => ['S-Way', 'X-Way', 'Eurocargo'],
        'Kenworth' => ['T680', 'W990', 'T880'],
        'Peterbilt' => ['579', '389', '567'],
        'Freightliner' => ['Cascadia', 'M2 106', '122SD'],
        'Renault Trucks' => ['T High', 'C 460', 'D Wide'],
    ];

    public function definition(): array
    {
        $vehicleType = VehicleType::query()->inRandomOrder()->first() ?? VehicleType::query()->create([
            'name' => 'Tractor Unit',
            'code' => 'TRACTOR_UNIT',
            'description' => 'Heavy tractor unit for long-haul freight operations.',
            'is_active' => true,
        ]);

        $fuelType = FuelType::query()->inRandomOrder()->first() ?? FuelType::query()->create([
            'name' => 'Diesel',
            'code' => 'DIESEL',
            'description' => 'Diesel fuel for heavy commercial freight vehicles.',
            'is_active' => true,
        ]);

        $statusId = self::vehicleStatusId('Available');
        $manufacturer = fake()->randomElement(array_keys(self::MANUFACTURERS));
        $model = fake()->randomElement(self::MANUFACTURERS[$manufacturer]);
        $sequence = fake()->unique()->numberBetween(1000, 9999);
        $routingClass = fake()->randomElement([
            Vehicle::ROUTING_CLASS_VAN,
            Vehicle::ROUTING_CLASS_LIGHT_TRUCK,
            Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
            Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
            Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
        ]);

        [$grossWeight, $height, $width, $length] = match ($routingClass) {
            Vehicle::ROUTING_CLASS_VAN => [
                fake()->numberBetween(2500, 3500),
                round(fake()->randomFloat(2, 2.4, 2.8), 2),
                round(fake()->randomFloat(2, 1.9, 2.2), 2),
                round(fake()->randomFloat(2, 5.0, 6.5), 2),
            ],
            Vehicle::ROUTING_CLASS_LIGHT_TRUCK => [
                fake()->numberBetween(5000, 12000),
                round(fake()->randomFloat(2, 2.7, 3.2), 2),
                round(fake()->randomFloat(2, 2.2, 2.45), 2),
                round(fake()->randomFloat(2, 6.5, 9.5), 2),
            ],
            default => [
                fake()->numberBetween(18000, 44000),
                round(fake()->randomFloat(2, 3.6, 4.0), 2),
                round(fake()->randomFloat(2, 2.45, 2.55), 2),
                round(fake()->randomFloat(2, 12.0, 16.5), 2),
            ],
        };

        $payload = fake()->numberBetween(
            (int) max(500, (int) ($grossWeight * 0.35)),
            max((int) ($grossWeight * 0.4) + 1, $grossWeight - 3000)
        );

        return [
            'operational_code' => sprintf('VH-%05d', $sequence),
            'fleet_number' => sprintf('FX-%04d', $sequence),
            'plate_number' => strtoupper(fake()->unique()->bothify('??-####-??')),
            'vin' => strtoupper(fake()->unique()->bothify('1FV########??????')),
            'manufacturer' => $manufacturer,
            'model' => $model,
            'production_year' => fake()->numberBetween(2016, (int) date('Y')),
            'vehicle_type_id' => $vehicleType->id,
            'routing_class' => $routingClass,
            'fuel_type_id' => $fuelType->id,
            'transmission' => fake()->randomElement(['manual', 'automatic', 'automated_manual']),
            'axle_count' => fake()->randomElement([2, 3, 4, 5, 6]),
            'payload_capacity_kg' => $payload,
            'gross_weight_kg' => $grossWeight,
            'height_m' => $height,
            'width_m' => $width,
            'length_m' => $length,
            'fuel_tank_capacity_l' => fake()->numberBetween(300, 1400),
            'current_odometer' => fake()->numberBetween(12000, 920000),
            'status_id' => $statusId,
            'is_active' => true,
            'gps_device_identifier' => 'GPS-' . strtoupper(Str::random(10)),
            'insurance_expiry_date' => fake()->dateTimeBetween('+1 month', '+18 months')->format('Y-m-d'),
            'registration_expiry_date' => fake()->dateTimeBetween('+1 month', '+24 months')->format('Y-m-d'),
            'inspection_expiry_date' => fake()->dateTimeBetween('+1 month', '+12 months')->format('Y-m-d'),
            'notes' => fake()->optional(0.7)->randomElement([
                'Assigned to long-haul international freight lanes.',
                'Equipped for customs-sealed container movements.',
                'Preferred for high-priority cross-border shipments.',
                'Telematics-ready vehicle with active GPS monitoring.',
                'Suitable for heavy payload dispatch and regional distribution.',
            ]),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(?int $statusId = null): static
    {
        return $this->state(fn () => [
            'status_id' => $statusId ?? self::vehicleStatusId('Available'),
            'is_active' => true,
        ]);
    }

    public function inactive(?int $statusId = null): static
    {
        return $this->state(fn () => [
            'status_id' => $statusId ?? self::vehicleStatusId('Inactive'),
            'is_active' => false,
        ]);
    }

    public static function vehicleStatusId(string $name): int
    {
        return Status::query()->firstOrCreate([
            'name' => $name,
            'type' => 'vehicle',
        ])->id;
    }
}
