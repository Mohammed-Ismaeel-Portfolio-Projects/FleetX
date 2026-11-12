<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use App\Enums\CustomerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic Driver ↔ User and Client ↔ Company demo links.
 * Never invents IDs for unrelated records; skips when targets missing.
 */
class IdentityLinkSeeder extends Seeder
{
    public function run(): void
    {
        $driverRole = Role::firstOrCreate(['name' => 'driver']);
        $clientRole = Role::firstOrCreate(['name' => 'client']);

        $driverUser = User::firstOrCreate(
            ['email' => 'driver@fleetx.com'],
            [
                'name' => 'Driver Demo',
                'password' => Hash::make('Driver@123'),
                'role_id' => $driverRole->id,
            ]
        );

        if ($driverUser->role_id !== $driverRole->id) {
            $driverUser->update(['role_id' => $driverRole->id]);
        }

        // Link exactly one Driver: prefer already linked to this user, else first unlinked by id.
        $driver = Driver::query()->where('user_id', $driverUser->id)->first()
            ?? Driver::query()->whereNull('user_id')->orderBy('id')->first();

        if ($driver && $driver->user_id === null) {
            $driver->update(['user_id' => $driverUser->id]);
        }

        // Demo companies must be priceable; production Admin configures customer_type explicitly.
        Company::query()->whereNull('customer_type')->update(['customer_type' => CustomerType::STANDARD->value]);
        $company = Company::query()->orderBy('id')->first();

        $clientUser = User::firstOrCreate(
            ['email' => 'client@fleetx.com'],
            [
                'name' => 'Client Demo',
                'password' => Hash::make('Client@123'),
                'role_id' => $clientRole->id,
                'company_id' => $company?->id,
            ]
        );

        if ($clientUser->role_id !== $clientRole->id) {
            $clientUser->update(['role_id' => $clientRole->id]);
        }

        if ($company && $clientUser->company_id === null) {
            $clientUser->update(['company_id' => $company->id]);
        }
    }
}
