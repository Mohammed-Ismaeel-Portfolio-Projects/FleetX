<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;

$base = getenv('FLEETX_API_BASE') ?: 'http://127.0.0.1:8001/api/v1';

$login = Http::post($base.'/auth/login', [
    'email' => 'dispatcher01@fleetx.test',
    'password' => 'Dispatcher@123',
]);

if (! $login->successful()) {
    fwrite(STDERR, 'LOGIN_FAIL '.$login->status().' '.$login->body().PHP_EOL);
    exit(1);
}

$token = $login->json('data.access_token')
    ?? $login->json('data.token')
    ?? $login->json('access_token');

if (! is_string($token) || $token === '') {
    fwrite(STDERR, 'TOKEN_MISSING '.json_encode($login->json()).PHP_EOL);
    exit(1);
}

echo "LOGIN_OK\n";

$ship = Shipment::query()
    ->whereHas('status', fn ($q) => $q->where('name', 'Waiting Assignment'))
    ->orderByDesc('id')
    ->first();

$driver = Driver::query()
    ->whereHas('user', fn ($q) => $q->where('email', 'driver01@fleetx.test'))
    ->first();

$vehicle = Vehicle::query()
    ->whereDoesntHave('activeAssignment')
    ->orderBy('id')
    ->first();

$status = Status::query()
    ->where('type', 'assignment')
    ->where('name', 'Assigned')
    ->first();

$dispatcher = User::query()->where('email', 'dispatcher01@fleetx.test')->first();

if (! $ship || ! $driver || ! $vehicle || ! $status || ! $dispatcher) {
    echo json_encode([
        'ship' => $ship?->id,
        'driver' => $driver?->id,
        'vehicle' => $vehicle?->id,
        'status' => $status?->id,
        'dispatcher' => $dispatcher?->id,
        'note' => 'Missing prerequisite for runtime assignment create',
    ], JSON_PRETTY_PRINT).PHP_EOL;
    exit(2);
}

$code = 'RT-'.date('ymdHis');
$payload = [
    'shipment_id' => $ship->id,
    'assignment_code' => $code,
    'driver_id' => $driver->id,
    'vehicle_id' => $vehicle->id,
    'trailer_id' => null,
    'status_id' => $status->id,
    'assigned_at' => now()->format('Y-m-d H:i:s'),
    'notes' => 'DISPATCHER-ASSIGNMENT-02 runtime',
];

$create = Http::withToken($token)
    ->acceptJson()
    ->post($base.'/assignments', $payload);

echo 'CREATE_STATUS='.$create->status().PHP_EOL;
$body = $create->json();
echo 'CREATE_SUCCESS='.json_encode($body['success'] ?? null).PHP_EOL;
echo 'MSG='.($body['message'] ?? '').PHP_EOL;

if (($body['errors']['assigned_by'] ?? null) !== null) {
    echo "ASSIGNED_BY_STILL_REQUIRED\n";
    exit(3);
}

$asg = Assignment::query()->where('assignment_code', $code)->first();

echo json_encode([
    'persisted' => (bool) $asg,
    'assignment_id' => $asg?->id,
    'assigned_by' => $asg?->assigned_by,
    'dispatcher_user_id' => $dispatcher->id,
    'assigned_by_matches_dispatcher' => $asg && (int) $asg->assigned_by === (int) $dispatcher->id,
    'driver_id' => $asg?->driver_id,
    'expected_driver_id' => $driver->id,
    'driver_user_id' => $driver->user_id,
    'driver_email' => $driver->email,
], JSON_PRETTY_PRINT).PHP_EOL;

if (! $asg) {
    exit(4);
}

$dLogin = Http::post($base.'/auth/login', [
    'email' => 'driver01@fleetx.test',
    'password' => 'Driver@123',
]);
$dToken = $dLogin->json('data.access_token') ?? $dLogin->json('data.token');
$work = Http::withToken($dToken)->acceptJson()->get($base.'/driver/work/active');
echo 'DRIVER_WORK_STATUS='.$work->status().PHP_EOL;
$wdata = $work->json('data');
$workId = is_array($wdata) ? ($wdata['id'] ?? $wdata['assignment']['id'] ?? null) : null;
$workCode = is_array($wdata) ? ($wdata['assignment_code'] ?? $wdata['assignment']['assignment_code'] ?? null) : null;

echo json_encode([
    'work_assignment_id' => $workId,
    'work_assignment_code' => $workCode,
    'matches_created_id' => $workId !== null && (string) $workId === (string) $asg->id,
    'matches_created_code' => $workCode !== null && $workCode === $code,
], JSON_PRETTY_PRINT).PHP_EOL;
