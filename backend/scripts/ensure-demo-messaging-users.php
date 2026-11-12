<?php

/**
 * Ensure M7 demo messaging users exist with known local passwords.
 * Does not invent Driver/Company links beyond IdentityLinkSeeder rules.
 *
 * Usage: php scripts/ensure-demo-messaging-users.php
 */

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$defs = [
    ['email' => 'admin@fleetx.com', 'name' => 'Sarah Admin', 'role' => 'admin', 'password' => 'password'],
    ['email' => 'dispatcher@fleetx.com', 'name' => 'Dispatcher Demo', 'role' => 'dispatcher', 'password' => 'Dispatcher@123'],
    ['email' => 'driver@fleetx.com', 'name' => 'Driver Demo', 'role' => 'driver', 'password' => 'Driver@123'],
    ['email' => 'client@fleetx.com', 'name' => 'Client Demo', 'role' => 'client', 'password' => 'Client@123'],
];

foreach ($defs as $def) {
    $role = Role::firstOrCreate(['name' => $def['role']]);
    $user = User::query()->where('email', $def['email'])->first();
    if (! $user) {
        $user = User::create([
            'email' => $def['email'],
            'name' => $def['name'],
            'password' => Hash::make($def['password']),
            'role_id' => $role->id,
        ]);
        echo "created {$def['email']}\n";
    } else {
        $user->forceFill([
            'name' => $def['name'],
            'password' => Hash::make($def['password']),
            'role_id' => $role->id,
        ])->save();
        echo "updated {$def['email']}\n";
    }
}

echo "OK\n";
