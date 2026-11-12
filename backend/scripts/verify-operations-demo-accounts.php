<?php

use App\Models\Driver;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$driverUsers = User::query()
    ->where('email', 'like', 'driver%@fleetx.test')
    ->whereHas('role', fn ($q) => $q->where('name', 'driver'))
    ->count();

$linkedDrivers = Driver::query()
    ->whereNotNull('user_id')
    ->whereHas('user', fn ($q) => $q->where('email', 'like', 'driver%@fleetx.test'))
    ->count();

$dispatchers = User::query()->where('email', 'like', 'dispatcher%@fleetx.test')->count();
$clients = User::query()->where('email', 'like', 'client%@fleetx.test')->count();
$vehicles = Vehicle::query()->count();
$trailers = Trailer::query()->count();

$checks = [
    'driver01' => ['email' => 'driver01@fleetx.test', 'password' => 'Driver@123'],
    'dispatcher01' => ['email' => 'dispatcher01@fleetx.test', 'password' => 'Dispatcher@123'],
    'client01' => ['email' => 'client01@fleetx.test', 'password' => 'Client@123'],
];

$authResults = [];
foreach ($checks as $label => $cred) {
    $user = User::query()->where('email', $cred['email'])->first();
    $authResults[$label] = $user && Hash::check($cred['password'], $user->password) ? 'PASS' : 'FAIL';
}

$driver01 = Driver::query()
    ->whereHas('user', fn ($q) => $q->where('email', 'driver01@fleetx.test'))
    ->first();

echo json_encode([
    'drivers_users' => $driverUsers,
    'linked_drivers' => $linkedDrivers,
    'dispatchers' => $dispatchers,
    'clients' => $clients,
    'vehicles' => $vehicles,
    'trailers' => $trailers,
    'password_hash_checks' => $authResults,
    'driver01_identity' => $driver01 ? [
        'driver_id' => $driver01->id,
        'user_id' => $driver01->user_id,
        'employee_number' => $driver01->employee_number,
        'email' => $driver01->email,
    ] : null,
], JSON_PRETTY_PRINT) . PHP_EOL;
