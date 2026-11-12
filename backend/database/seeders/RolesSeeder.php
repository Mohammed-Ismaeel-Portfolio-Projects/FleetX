<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Seed the roles table.
     *
     * Idempotent: safe to run multiple times via updateOrCreate().
     */
    public function run(): void
    {
        $roles = [
            'admin',
            'dispatcher',
            'driver',
            'client',
        ];

        foreach ($roles as $name) {
            Role::updateOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}
