<?php

namespace Database\Factories;

use App\Models\Status;
use App\Models\Trailer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trailer>
 */
class TrailerFactory extends Factory
{
    protected $model = Trailer::class;

    private const TRAILER_TYPES = [
        'Dry Van' => [
            'manufacturers' => ['Great Dane', 'Wabash National', 'Utility Trailer', 'Hyundai Translead', 'Stoughton'],
            'models' => ['SuperSeal', 'Freedom', '4000D-X', 'DryMax', 'Aerodynamic'],
            'axle_count' => [2, 3],
            'payload_kg' => [20000, 28000],
            'length_m' => [13.7, 14.6],
            'width_m' => [2.6, 2.6],
            'height_m' => [2.7, 2.9],
            'volume_m3' => [85, 105],
        ],
        'Refrigerated Trailer' => [
            'manufacturers' => ['Great Dane', 'Wabash National', 'Utility Trailer', 'Hyundai Translead', 'Schmitz Cargobull'],
            'models' => ['ThermoGuard', 'Arctic', 'Reefer', 'CoolMax', 'TempControl'],
            'axle_count' => [2, 3],
            'payload_kg' => [18000, 25000],
            'length_m' => [13.7, 14.6],
            'width_m' => [2.6, 2.6],
            'height_m' => [2.7, 2.9],
            'volume_m3' => [80, 100],
        ],
        'Flatbed Trailer' => [
            'manufacturers' => ['Great Dane', 'Wabash National', 'Fontaine', 'Manac', 'Trailmobile'],
            'models' => ['Flatbed', 'Drop Deck', 'Standard', 'Heavy Duty', 'Platform'],
            'axle_count' => [2, 3],
            'payload_kg' => [22000, 30000],
            'length_m' => [12.2, 14.6],
            'width_m' => [2.4, 2.6],
            'height_m' => [1.2, 1.5],
            'volume_m3' => [null, null],
        ],
        'Lowboy' => [
            'manufacturers' => ['Fontaine', 'Trail King', 'Rogers', 'Manac', 'Coles'],
            'models' => ['Lowboy', 'Detachable Gooseneck', 'Heavy Haul', 'Specialized', 'Multi-Axle'],
            'axle_count' => [3, 4, 5],
            'payload_kg' => [35000, 50000],
            'length_m' => [10.0, 13.7],
            'width_m' => [2.4, 3.0],
            'height_m' => [0.8, 1.2],
            'volume_m3' => [null, null],
        ],
        'Container Chassis' => [
            'manufacturers' => ['Cheetah', 'Chassis King', 'Fontaine', 'Strick', 'Manac'],
            'models' => ['20ft Chassis', '40ft Chassis', 'Combo', 'Extendable', 'Tandem'],
            'axle_count' => [2, 3],
            'payload_kg' => [25000, 32000],
            'length_m' => [6.1, 12.2],
            'width_m' => [2.4, 2.6],
            'height_m' => [1.2, 1.5],
            'volume_m3' => [null, null],
        ],
        'Tanker Trailer' => [
            'manufacturers' => ['Polar', 'MCN', 'Heil', 'Walker', 'Brenner'],
            'models' => ['Fuel Tanker', 'Chemical Tanker', 'Food Grade', 'Stainless Steel', 'Aluminum'],
            'axle_count' => [3, 4],
            'payload_kg' => [28000, 40000],
            'length_m' => [11.0, 13.7],
            'width_m' => [2.4, 2.6],
            'height_m' => [3.0, 3.8],
            'volume_m3' => [35, 50],
        ],
        'Curtainsider' => [
            'manufacturers' => ['Schmitz Cargobull', 'Kogel', 'Krone', 'Great Dane', 'Wabash'],
            'models' => ['Curtain', 'FlexiCurtain', 'Slide', 'EasyLoad', 'QuickAccess'],
            'axle_count' => [2, 3],
            'payload_kg' => [20000, 28000],
            'length_m' => [13.7, 14.6],
            'width_m' => [2.6, 2.6],
            'height_m' => [2.7, 2.9],
            'volume_m3' => [85, 105],
        ],
        'Walking Floor' => [
            'manufacturers' => ['Keen', 'Mac Trailer', 'Pik Rite', 'Great Dane', 'Wabash'],
            'models' => ['Walking Floor', 'Live Floor', 'Self-Unload', 'Bulk', 'Aggregate'],
            'axle_count' => [2, 3],
            'payload_kg' => [25000, 35000],
            'length_m' => [10.7, 13.7],
            'width_m' => [2.4, 2.6],
            'height_m' => [2.7, 3.2],
            'volume_m3' => [70, 95],
        ],
        'Tipper Trailer' => [
            'manufacturers' => ['Mac Trailer', 'Pik Rite', 'Clement', 'Travis', 'Aero'],
            'models' => ['Tipper', 'Dump', 'End Dump', 'Side Dump', 'Bottom Dump'],
            'axle_count' => [2, 3],
            'payload_kg' => [25000, 35000],
            'length_m' => [8.5, 12.2],
            'width_m' => [2.4, 2.6],
            'height_m' => [2.5, 3.5],
            'volume_m3' => [30, 50],
        ],
        'Car Carrier' => [
            'manufacturers' => ['Cottrell', 'Kaufman', 'Big Tex', 'PJ Trailers', 'Wells Cargo'],
            'models' => ['Car Carrier', 'Auto Hauler', 'Multi-Level', 'Stacker', 'Enclosed'],
            'axle_count' => [2, 3],
            'payload_kg' => [8000, 15000],
            'length_m' => [14.6, 18.3],
            'width_m' => [2.4, 2.6],
            'height_m' => [2.7, 4.0],
            'volume_m3' => [null, null],
        ],
    ];

    public function definition(): array
    {
        $statusId = self::trailerStatusId('Available');
        $trailerType = fake()->randomElement(array_keys(self::TRAILER_TYPES));
        $typeConfig = self::TRAILER_TYPES[$trailerType];
        $manufacturer = fake()->randomElement($typeConfig['manufacturers']);
        $model = fake()->randomElement($typeConfig['models']);
        $sequence = fake()->unique()->numberBetween(1000, 9999);
        $axleCount = fake()->randomElement($typeConfig['axle_count']);
        $payload = fake()->numberBetween($typeConfig['payload_kg'][0], $typeConfig['payload_kg'][1]);
        $length = fake()->randomElement($typeConfig['length_m']);
        $width = fake()->randomElement($typeConfig['width_m']);
        $height = fake()->randomElement($typeConfig['height_m']);
        $volume = fake()->randomElement($typeConfig['volume_m3']);

        return [
            'trailer_code' => sprintf('TR-%05d', $sequence),
            'fleet_number' => sprintf('FT-%04d', $sequence),
            'plate_number' => strtoupper(fake()->unique()->bothify('??-####-??')),
            'trailer_type' => $trailerType,
            'manufacturer' => $manufacturer,
            'model' => $model,
            'production_year' => fake()->numberBetween(2016, (int) date('Y')),
            'axle_count' => $axleCount,
            'payload_capacity_kg' => $payload,
            'length_m' => $length,
            'width_m' => $width,
            'height_m' => $height,
            'cargo_volume_m3' => $volume,
            'current_odometer' => fake()->numberBetween(5000, 450000),
            'gps_device_identifier' => 'GPS-' . strtoupper(Str::random(10)),
            'insurance_expiry_date' => fake()->dateTimeBetween('+1 month', '+18 months')->format('Y-m-d'),
            'inspection_expiry_date' => fake()->dateTimeBetween('+1 month', '+12 months')->format('Y-m-d'),
            'registration_expiry_date' => fake()->dateTimeBetween('+1 month', '+24 months')->format('Y-m-d'),
            'status_id' => $statusId,
            'is_active' => true,
            'notes' => fake()->optional(0.7)->randomElement([
                'Assigned to regional distribution routes.',
                'Equipped for temperature-sensitive cargo.',
                'Suitable for heavy machinery transport.',
                'Preferred for container logistics operations.',
                'Telematics-ready trailer with active GPS monitoring.',
                'Designed for bulk material transport.',
                'Multi-purpose trailer for various cargo types.',
                'Specialized for automotive logistics.',
            ]),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(?int $statusId = null): static
    {
        return $this->state(fn () => [
            'status_id' => $statusId ?? self::trailerStatusId('Available'),
            'is_active' => true,
        ]);
    }

    public function inactive(?int $statusId = null): static
    {
        return $this->state(fn () => [
            'status_id' => $statusId ?? self::trailerStatusId('Inactive'),
            'is_active' => false,
        ]);
    }

    public static function trailerStatusId(string $name): int
    {
        return Status::query()->firstOrCreate([
            'name' => $name,
            'type' => 'trailer',
        ])->id;
    }
}
