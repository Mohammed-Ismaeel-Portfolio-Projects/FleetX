<?php

namespace Tests\Support;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Hash;

trait CreatesDispatcherUser
{
    protected function makeDispatcherUser(): User
    {
        if (Role::query()->where('name', 'dispatcher')->doesntExist()) {
            $this->seed(RolesSeeder::class);
        }

        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();

        return User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }
}
