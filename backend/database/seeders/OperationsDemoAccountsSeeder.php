<?php

namespace Database\Seeders;

use App\Enums\CustomerType;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DISPATCHER-REQUESTS-01 — deterministic local demo accounts for end-to-end ops demos.
 *
 * Credentials (local demo only):
 *   Drivers:     driver01@fleetx.test … driver40@fleetx.test  /  Driver@123
 *   Dispatchers: dispatcher01@fleetx.test … dispatcher03@fleetx.test  /  Dispatcher@123
 *   Clients:     client01@fleetx.test … client10@fleetx.test  /  Client@123
 *
 * Idempotent: upsert by stable email / company_code. Does not wipe unrelated data.
 * Companies always get customer_type so Client pricing estimate works.
 */
class OperationsDemoAccountsSeeder extends Seeder
{
    public const DRIVER_PASSWORD = 'Driver@123';

    public const DISPATCHER_PASSWORD = 'Dispatcher@123';

    public const CLIENT_PASSWORD = 'Client@123';

    public const DRIVER_COUNT = 40;

    public const DISPATCHER_COUNT = 3;

    public const CLIENT_COUNT = 10;

    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            StatusesSeeder::class,
        ]);

        $driverRole = Role::query()->firstOrCreate(['name' => 'driver']);
        $dispatcherRole = Role::query()->firstOrCreate(['name' => 'dispatcher']);
        $clientRole = Role::query()->firstOrCreate(['name' => 'client']);

        $availableStatus = Status::query()->firstOrCreate(
            ['name' => 'Available', 'type' => 'driver'],
            ['name' => 'Available', 'type' => 'driver']
        );

        $this->seedDispatchers($dispatcherRole);
        $this->seedClients($clientRole);
    }

    private function seedDispatchers(Role $dispatcherRole): void
    {
        for ($i = 1; $i <= self::DISPATCHER_COUNT; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $email = "dispatcher{$n}@fleetx.test";

            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => "Dispatcher {$n}",
                    'password' => Hash::make(self::DISPATCHER_PASSWORD),
                    'role_id' => $dispatcherRole->id,
                    'company_id' => null,
                ]
            );
        }
    }

    private function seedClients(Role $clientRole): void
    {
        $customerTypes = [
            CustomerType::STANDARD,
            CustomerType::CORPORATE,
            CustomerType::VIP,
            CustomerType::LONG_TERM,
        ];

        for ($i = 1; $i <= self::CLIENT_COUNT; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $code = "OPS-CLI-{$n}";
            $email = "client{$n}@fleetx.test";
            $customerType = $customerTypes[($i - 1) % count($customerTypes)];

            $company = Company::query()->where('company_code', $code)->first()
                ?? Company::factory()->create([
                    'company_code' => $code,
                    'company_name' => "Ops Demo Client {$n}",
                    'legal_name' => "Ops Demo Client {$n} Co",
                    'customer_type' => $customerType->value,
                ]);

            if ($company->customer_type === null) {
                $company->update(['customer_type' => $customerType]);
            }

            $clientUserData = [
                'name' => "Client {$n}",
                'password' => Hash::make(self::CLIENT_PASSWORD),
                'role_id' => $clientRole->id,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'company_id')) {
                $clientUserData['company_id'] = $company->id;
            }

            User::query()->updateOrCreate(
                ['email' => $email],
                $clientUserData
            );
        }
    }

    private function seedDrivers(Role $driverRole, int $availableStatusId): void
    {
        $firstNames = [
            'Ahmad', 'Sara', 'Omar', 'Layla', 'Karim', 'Nour', 'Hassan', 'Maya',
            'Youssef', 'Rana', 'Tarek', 'Hiba', 'Fadi', 'Dina', 'Samir', 'Lina',
            'Walid', 'Rima', 'Bassel', 'Jana',
        ];
        $lastNames = [
            'Khalil', 'Haddad', 'Nasser', 'Saleh', 'Farhat', 'Mansour', 'Abbas', 'Karam',
            'Rahman', 'Zein', 'Darwish', 'Qasim', 'Hamdan', 'Barakat', 'Safi', 'Aoun',
            'Jaber', 'Halabi', 'Shami', 'Masri',
        ];

        for ($i = 1; $i <= self::DRIVER_COUNT; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $email = "driver{$n}@fleetx.test";
            $employeeNumber = sprintf('OPS-DRV-%02d', $i);
            $firstName = $firstNames[($i - 1) % count($firstNames)];
            $lastName = $lastNames[($i - 1) % count($lastNames)];

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => "{$firstName} {$lastName}",
                    'password' => Hash::make(self::DRIVER_PASSWORD),
                    'role_id' => $driverRole->id,
                    'company_id' => null,
                ]
            );

            $driver = Driver::query()->where('user_id', $user->id)->first()
                ?? Driver::query()->where('employee_number', $employeeNumber)->first()
                ?? Driver::query()->where('email', $email)->first();

            if ($driver === null) {
                $driver = Driver::factory()->create([
                    'user_id' => $user->id,
                    'employee_number' => $employeeNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => sprintf('+96399900%04d', $i),
                    'status_id' => $availableStatusId,
                    'is_active' => true,
                    'license_number' => sprintf('OPS-DL-%02d', $i),
                    'license_class' => 'CE',
                ]);
            } else {
                $driver->update([
                    'user_id' => $user->id,
                    'employee_number' => $employeeNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'status_id' => $availableStatusId,
                    'is_active' => true,
                ]);
            }
        }
    }
}
