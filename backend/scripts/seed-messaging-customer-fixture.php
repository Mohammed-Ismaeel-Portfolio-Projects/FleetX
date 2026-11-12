<?php

/**
 * Ensure a dispatcher_customer conversation exists for M7 e2e verification.
 * Usage: php scripts/seed-messaging-customer-fixture.php
 * Prints conversation UUID on success.
 */

use App\Actions\Conversation\OpenConversationAction;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dispatcher = User::query()->where('email', 'dispatcher@fleetx.com')->first();
$clientUser = User::query()->where('email', 'client@fleetx.com')->first();

if (! $dispatcher || ! $clientUser) {
    fwrite(STDERR, "Missing dispatcher/client users. Seed UserSeeder + IdentityLinkSeeder.\n");
    exit(1);
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

$existing = Conversation::query()
    ->where('type', Conversation::TYPE_DISPATCHER_CUSTOMER)
    ->whereHas('participants', fn ($q) => $q->where('user_id', $clientUser->id))
    ->orderByDesc('updated_at')
    ->first();

if ($existing) {
    echo $existing->id.PHP_EOL;
    exit(0);
}

$shipment = Shipment::query()
    ->where('customer_company_id', $company->id)
    ->orderByDesc('created_at')
    ->first();

if (! $shipment) {
    $shipment = Shipment::factory()->create([
        'customer_company_id' => $company->id,
    ]);
}

$result = app(OpenConversationAction::class)->execute($dispatcher, [
    'type' => Conversation::TYPE_DISPATCHER_CUSTOMER,
    'shipment_id' => $shipment->id,
    'client_user_id' => $clientUser->id,
]);

if (! $result->success) {
    fwrite(STDERR, 'Open failed: '.$result->message.PHP_EOL);
    exit(1);
}

echo $result->data->id.PHP_EOL;
