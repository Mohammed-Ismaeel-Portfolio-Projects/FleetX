<?php

/**
 * Ensure two dispatcher_driver + two dispatcher_customer conversations
 * for M8 multi-conversation isolation verification.
 *
 * Writes storage/app/m8-isolation-fixture.json
 * Usage: php scripts/seed-m8-isolation-fixture.php
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

$dispatcher = User::query()->where('email', 'dispatcher@fleetx.com')->first()
    ?? User::query()->where('email', 'admin@fleetx.com')->first();
$driverUser = User::query()->where('email', 'driver@fleetx.com')->first();
$clientUser = User::query()->where('email', 'client@fleetx.com')->first();

if (! $dispatcher || ! $driverUser || ! $clientUser) {
    fwrite(STDERR, "Missing demo users. Run ensure-demo-messaging-users.php + IdentityLinkSeeder.\n");
    exit(1);
}

$driver = Driver::query()->where('user_id', $driverUser->id)->first();
if (! $driver) {
    $driverStatus = Status::firstOrCreate(
        ['name' => 'Available', 'type' => 'driver'],
        ['name' => 'Available', 'type' => 'driver']
    );
    $unlinked = Driver::query()->whereNull('user_id')->orderBy('id')->first();
    if ($unlinked) {
        $unlinked->update(['user_id' => $driverUser->id]);
        $driver = $unlinked;
    } else {
        $driver = Driver::factory()->create([
            'user_id' => $driverUser->id,
            'status_id' => $driverStatus->id,
            'is_active' => true,
        ]);
    }
}

$clientUser->loadMissing('company');
$company = $clientUser->company;
if (! $company) {
    $company = Company::withoutEvents(function () {
        return Company::factory()->create([
            'id' => (string) Str::uuid(),
            'created_by' => null,
            'updated_by' => null,
        ]);
    });
    $clientUser->company_id = $company->id;
    $clientUser->save();
}

$assignmentStatus = Status::firstOrCreate(
    ['name' => 'In Operation', 'type' => 'assignment'],
    ['name' => 'In Operation', 'type' => 'assignment']
);

$openDriver = function () use ($dispatcher, $driver, $driverUser, $assignmentStatus, $company) {
    // One active assignment per driver — deactivate prior before creating another.
    Assignment::query()
        ->where('driver_id', $driver->id)
        ->where('is_active', true)
        ->update(['is_active' => false, 'ended_at' => now()]);

    $shipment = Shipment::factory()->create([
        'customer_company_id' => $company->id,
    ]);
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
        throw new RuntimeException('driver open: '.$result->message);
    }

    return $result->data->id;
};

$openCustomer = function () use ($dispatcher, $clientUser, $company) {
    $shipment = Shipment::factory()->create([
        'customer_company_id' => $company->id,
    ]);
    $result = app(OpenConversationAction::class)->execute($dispatcher, [
        'type' => Conversation::TYPE_DISPATCHER_CUSTOMER,
        'shipment_id' => $shipment->id,
        'client_user_id' => $clientUser->id,
    ]);
    if (! $result->success) {
        throw new RuntimeException('customer open: '.$result->message);
    }

    return $result->data->id;
};

$driverIds = Conversation::query()
    ->where('type', Conversation::TYPE_DISPATCHER_DRIVER)
    ->whereHas('participants', fn ($q) => $q->where('user_id', $driverUser->id))
    ->orderBy('id')
    ->limit(2)
    ->pluck('id')
    ->all();

while (count($driverIds) < 2) {
    $driverIds[] = $openDriver();
}

$customerIds = Conversation::query()
    ->where('type', Conversation::TYPE_DISPATCHER_CUSTOMER)
    ->whereHas('participants', fn ($q) => $q->where('user_id', $clientUser->id))
    ->orderBy('id')
    ->limit(2)
    ->pluck('id')
    ->all();

while (count($customerIds) < 2) {
    $customerIds[] = $openCustomer();
}

$payload = [
    'driver' => array_values(array_slice($driverIds, 0, 2)),
    'customer' => array_values(array_slice($customerIds, 0, 2)),
];

$path = storage_path('app/m8-isolation-fixture.json');
file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT).PHP_EOL);

echo json_encode($payload).PHP_EOL;
