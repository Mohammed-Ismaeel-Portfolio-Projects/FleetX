<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $dispatcherRole = Role::firstOrCreate(['name' => 'dispatcher']);

        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@fleetx.com'],
            [
                'name' => 'Sarah Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
            ]
        );

        // Create dispatcher user
        User::firstOrCreate(
            ['email' => 'dispatcher@fleetx.com'],
            [
                'name' => 'Dispatcher Demo',
                'password' => Hash::make('Dispatcher@123'),
                'role_id' => $dispatcherRole->id,
            ]
        );
    }
}
