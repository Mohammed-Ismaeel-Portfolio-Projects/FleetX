<?php

/**
 * Ensure a dispatcher_driver conversation exists for M6 e2e verification.
 * Usage: php scripts/seed-messaging-realtime-fixture.php
 * Prints conversation UUID on success.
 */

use App\Actions\Conversation\OpenConversationAction;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dispatcher = User::query()->where('email', 'dispatcher@fleetx.com')->first();
$driverUser = User::query()->where('email', 'driver@fleetx.com')->first();

if (! $dispatcher || ! $driverUser) {
    fwrite(STDERR, "Missing dispatcher/driver users. Seed UserSeeder + IdentityLinkSeeder.\n");
    exit(1);
}

$driver = Driver::query()->where('user_id', $driverUser->id)->first();
if (! $driver) {
    $status = Status::firstOrCreate(
        ['name' => 'Available', 'type' => 'driver'],
        ['name' => 'Available', 'type' => 'driver']
    );
    $driver = Driver::factory()->create([
        'user_id' => $driverUser->id,
        'status_id' => $status->id,
        'is_active' => true,
    ]);
}

$existing = Conversation::query()
    ->where('type', Conversation::TYPE_DISPATCHER_DRIVER)
    ->whereHas('participants', fn ($q) => $q->where('user_id', $driverUser->id))
    ->orderByDesc('updated_at')
    ->first();

if ($existing) {
    echo $existing->id.PHP_EOL;
    exit(0);
}

$company = Company::withoutEvents(function () {
    return Company::factory()->create([
        'id' => (string) Str::uuid(),
        'created_by' => null,
        'updated_by' => null,
    ]);
});

$shipment = Shipment::factory()->create([
    'customer_company_id' => $company->id,
]);

$assignmentStatus = Status::firstOrCreate(
    ['name' => 'In Operation', 'type' => 'assignment'],
    ['name' => 'In Operation', 'type' => 'assignment']
);

Assignment::factory()->create([
    'shipment_id' => $shipment->id,
    'driver_id' => $driver->id,
    'is_active' => true,
    'status_id' => $assignmentStatus->id,
    'assigned_by' => $dispatcher->id,
    'vehicle_id' => null,
    'trailer_id' => null,
]);

$result = app(OpenConversationAction::class)->execute($dispatcher, [
    'type' => Conversation::TYPE_DISPATCHER_DRIVER,
    'shipment_id' => $shipment->id,
    'driver_user_id' => $driverUser->id,
]);

if (! $result->success) {
    fwrite(STDERR, 'Open failed: '.$result->message.PHP_EOL);
    exit(1);
}

echo $result->data->id.PHP_EOL;
