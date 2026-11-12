<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Status;
use App\Enums\CustomerType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    private const LOCATIONS = [
        ['country' => 'US', 'state' => 'California', 'city' => 'Los Angeles', 'postal' => '90017', 'timezone' => 'America/Los_Angeles', 'currency' => 'USD', 'language' => 'en', 'lat' => 34.052235, 'lng' => -118.243683],
        ['country' => 'US', 'state' => 'Illinois', 'city' => 'Chicago', 'postal' => '60606', 'timezone' => 'America/Chicago', 'currency' => 'USD', 'language' => 'en', 'lat' => 41.878113, 'lng' => -87.629799],
        ['country' => 'US', 'state' => 'New York', 'city' => 'New York', 'postal' => '10004', 'timezone' => 'America/New_York', 'currency' => 'USD', 'language' => 'en', 'lat' => 40.712776, 'lng' => -74.005974],
        ['country' => 'CA', 'state' => 'Ontario', 'city' => 'Toronto', 'postal' => 'M5V 2T6', 'timezone' => 'America/Toronto', 'currency' => 'CAD', 'language' => 'en', 'lat' => 43.653225, 'lng' => -79.383186],
        ['country' => 'DE', 'state' => 'Berlin', 'city' => 'Berlin', 'postal' => '10115', 'timezone' => 'Europe/Berlin', 'currency' => 'EUR', 'language' => 'de', 'lat' => 52.520008, 'lng' => 13.404954],
        ['country' => 'DE', 'state' => 'Bavaria', 'city' => 'Munich', 'postal' => '80331', 'timezone' => 'Europe/Berlin', 'currency' => 'EUR', 'language' => 'de', 'lat' => 48.135124, 'lng' => 11.581981],
        ['country' => 'GB', 'state' => 'England', 'city' => 'London', 'postal' => 'E14 5AB', 'timezone' => 'Europe/London', 'currency' => 'GBP', 'language' => 'en', 'lat' => 51.507351, 'lng' => -0.127758],
        ['country' => 'GB', 'state' => 'England', 'city' => 'Manchester', 'postal' => 'M2 5DB', 'timezone' => 'Europe/London', 'currency' => 'GBP', 'language' => 'en', 'lat' => 53.480759, 'lng' => -2.242631],
        ['country' => 'FR', 'state' => 'Ile-de-France', 'city' => 'Paris', 'postal' => '75008', 'timezone' => 'Europe/Paris', 'currency' => 'EUR', 'language' => 'fr', 'lat' => 48.856613, 'lng' => 2.352222],
        ['country' => 'NL', 'state' => 'North Holland', 'city' => 'Amsterdam', 'postal' => '1012 JS', 'timezone' => 'Europe/Amsterdam', 'currency' => 'EUR', 'language' => 'nl', 'lat' => 52.367573, 'lng' => 4.904139],
        ['country' => 'NL', 'state' => 'South Holland', 'city' => 'Rotterdam', 'postal' => '3011 TA', 'timezone' => 'Europe/Amsterdam', 'currency' => 'EUR', 'language' => 'nl', 'lat' => 51.924419, 'lng' => 4.477733],
        ['country' => 'ES', 'state' => 'Community of Madrid', 'city' => 'Madrid', 'postal' => '28013', 'timezone' => 'Europe/Madrid', 'currency' => 'EUR', 'language' => 'es', 'lat' => 40.416775, 'lng' => -3.703790],
        ['country' => 'ES', 'state' => 'Catalonia', 'city' => 'Barcelona', 'postal' => '08039', 'timezone' => 'Europe/Madrid', 'currency' => 'EUR', 'language' => 'es', 'lat' => 41.385063, 'lng' => 2.173404],
        ['country' => 'IT', 'state' => 'Lombardy', 'city' => 'Milan', 'postal' => '20121', 'timezone' => 'Europe/Rome', 'currency' => 'EUR', 'language' => 'it', 'lat' => 45.464203, 'lng' => 9.189982],
        ['country' => 'PL', 'state' => 'Masovian', 'city' => 'Warsaw', 'postal' => '00-001', 'timezone' => 'Europe/Warsaw', 'currency' => 'PLN', 'language' => 'pl', 'lat' => 52.229676, 'lng' => 21.012229],
        ['country' => 'TR', 'state' => 'Istanbul', 'city' => 'Istanbul', 'postal' => '34010', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY', 'language' => 'tr', 'lat' => 41.008240, 'lng' => 28.978359],
        ['country' => 'AE', 'state' => 'Dubai', 'city' => 'Dubai', 'postal' => '00000', 'timezone' => 'Asia/Dubai', 'currency' => 'AED', 'language' => 'en', 'lat' => 25.204849, 'lng' => 55.270782],
        ['country' => 'SA', 'state' => 'Riyadh Province', 'city' => 'Riyadh', 'postal' => '12271', 'timezone' => 'Asia/Riyadh', 'currency' => 'SAR', 'language' => 'ar', 'lat' => 24.713552, 'lng' => 46.675297],
        ['country' => 'JO', 'state' => 'Amman', 'city' => 'Amman', 'postal' => '11118', 'timezone' => 'Asia/Amman', 'currency' => 'JOD', 'language' => 'ar', 'lat' => 31.953949, 'lng' => 35.910637],
    ];

    private const COMPANY_PREFIXES = [
        'Global', 'Fast', 'Blue Ocean', 'Eagle', 'North Star', 'Atlas', 'Fleet', 'Prime',
        'Summit', 'Harbor', 'Continental', 'Metro', 'Pioneer', 'Apex', 'Bridgeway',
    ];

    private const COMPANY_SUFFIXES = [
        'Logistics', 'Cargo', 'Transport', 'Freight', 'Shipping', 'Supply Chain',
        'Distribution', 'Forwarding', 'Express', 'Intermodal',
    ];

    private const LEGAL_SUFFIXES = [
        'Inc.', 'LLC', 'Ltd.', 'GmbH', 'B.V.', 'S.A.', 'S.p.A.', 'Sp. z o.o.',
        'A.S.', 'FZ-LLC', 'Limited',
    ];

    private const NOTES = [
        'Primary client for regional distribution lanes and scheduled full-truckload moves.',
        'Requires customs-ready documentation for cross-border freight operations.',
        'High-volume account with seasonal demand around retail replenishment cycles.',
        'Uses temperature-controlled vehicles for sensitive cargo and pharmaceutical shipments.',
        'Prefers weekly consolidated invoicing and proactive shipment status updates.',
        'Strategic logistics partner for port drayage and last-mile delivery coordination.',
        'Demo account used to validate dispatch, tracking, and pricing workflows.',
        'Operates a mixed shipment profile across palletized cargo, containers, and express freight.',
    ];

    public function definition(): array
    {
        $location = fake()->randomElement(self::LOCATIONS);
        $identity = self::demoIdentity(fake()->unique()->numberBetween(1000, 9999), $location['city']);
        $createdBy = fake()->optional(0.85)->uuid();

        return [
            ...$identity,
            'tax_number' => $this->taxNumber($location['country']),
            'vat_number' => $this->vatNumber($location['country']),
            'business_registration_number' => strtoupper(fake()->bothify('BRN-########')),
            'industry_id' => $this->randomLookupId('industries'),
            'company_size_id' => $this->randomLookupId('company_sizes'),
            'primary_phone' => fake()->e164PhoneNumber(),
            'website' => 'https://www.' . Str::slug($identity['company_name']) . '.com',
            'country' => $location['country'],
            'state_province' => $location['state'],
            'district' => fake()->optional(0.45)->randomElement(['Industrial Zone', 'Port District', 'Logistics Park', 'Business Bay', 'Free Zone']),
            'city' => $location['city'],
            'building_number' => (string) fake()->numberBetween(1, 900),
            'address_line_1' => fake()->streetName() . ' ' . fake()->randomElement(['Logistics Center', 'Freight Terminal', 'Distribution Hub', 'Business Park']),
            'address_line_2' => fake()->optional(0.35)->randomElement(['Suite 200', 'Warehouse B', 'Dock 7', 'Unit 12', 'Floor 4']),
            'postal_code' => $location['postal'],
            'latitude' => fake()->latitude($location['lat'] - 0.08, $location['lat'] + 0.08),
            'longitude' => fake()->longitude($location['lng'] - 0.08, $location['lng'] + 0.08),
            'timezone' => $location['timezone'],
            'currency_code' => $location['currency'],
            // Required for Client/Admin pricing calculate + submit snapshots.
            // Explicit null remains valid in tests that assert the configuration gate.
            'customer_type' => CustomerType::STANDARD->value,
            'language_code' => $location['language'],
            'status_id' => self::companyStatusId('active'),
            'is_active' => true,
            'notes' => fake()->optional(0.85)->randomElement(self::NOTES),
            'logo_path' => fake()->optional(0.25)->randomElement([
                'companies/logos/global-logistics.svg',
                'companies/logos/fleet-express.svg',
                'companies/logos/atlas-shipping.svg',
                'companies/logos/prime-cargo.svg',
            ]),
            'created_by' => $createdBy,
            'updated_by' => fake()->optional(0.65)->randomElement([$createdBy, fake()->uuid()]),
            'created_at' => fake()->dateTimeBetween('-18 months', '-2 days'),
            'updated_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function active(?int $statusId = null): static
    {
        return $this->state(fn () => [
            'status_id' => $statusId ?? self::companyStatusId('active'),
            'is_active' => true,
        ]);
    }

    public function inactive(?int $statusId = null): static
    {
        return $this->state(fn () => [
            'status_id' => $statusId ?? self::companyStatusId('inactive'),
            'is_active' => false,
            'notes' => fake()->randomElement([
                'Account is temporarily inactive pending contract renewal.',
                'Inactive demo account retained for reporting and restore workflow testing.',
                'Operations paused while billing and compliance details are reviewed.',
            ]),
        ]);
    }

    public static function demoIdentity(int $index, ?string $city = null): array
    {
        $prefix = self::COMPANY_PREFIXES[$index % count(self::COMPANY_PREFIXES)];
        $suffix = self::COMPANY_SUFFIXES[(int) floor($index / count(self::COMPANY_PREFIXES)) % count(self::COMPANY_SUFFIXES)];
        $cityPart = $city ? $city . ' ' : '';
        $companyName = trim("{$prefix} {$cityPart}{$suffix}");
        $legalName = $companyName . ' ' . self::LEGAL_SUFFIXES[$index % count(self::LEGAL_SUFFIXES)];
        $codePrefix = collect(explode(' ', Str::slug($companyName, ' ')))
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->take(3)
            ->implode('');

        return [
            'company_name' => $companyName,
            'legal_name' => $legalName,
            'company_code' => sprintf('%s-%04d', $codePrefix, $index + 1),
            'primary_email' => 'operations+' . sprintf('%03d', $index + 1) . '@' . Str::slug($companyName) . '.example.com',
        ];
    }

    public static function locationForIndex(int $index): array
    {
        return self::LOCATIONS[$index % count(self::LOCATIONS)];
    }

    public static function companyStatusId(string $name): int
    {
        return Status::query()->firstOrCreate([
            'name' => $name,
            'type' => 'company',
        ])->id;
    }

    private function randomLookupId(string $table): ?int
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->inRandomOrder()->value('id');
    }

    private function taxNumber(string $country): string
    {
        return match ($country) {
            'US' => fake()->numerify('##-#######'),
            'CA' => fake()->numerify('#########RT0001'),
            'GB' => fake()->numerify('##########'),
            'DE', 'FR', 'NL', 'ES', 'IT', 'PL' => fake()->numerify('###########'),
            'AE', 'SA', 'JO', 'TR' => fake()->numerify('###############'),
            default => fake()->numerify('##########'),
        };
    }

    private function vatNumber(string $country): ?string
    {
        return match ($country) {
            'US', 'CA' => null,
            default => $country . fake()->numerify('#########'),
        };
    }
}
