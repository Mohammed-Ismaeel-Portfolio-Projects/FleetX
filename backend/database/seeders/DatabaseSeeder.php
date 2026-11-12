<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\DriverSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            StatusesSeeder::class,
            ShipmentStatusesSeeder::class,
            ShipmentOrderStatusesSeeder::class,
            VehicleTypeSeeder::class,
            FuelTypeSeeder::class,
            VehicleStatusSeeder::class,
            PaymentMethodsSeeder::class,
            UserSeeder::class,
            PricingPolicySeeder::class,
            OperationsDemoAccountsSeeder::class,
            IdentityLinkSeeder::class,
            MasterDataSeeder::class,
        ]);
    }
}
