<?php

use App\Models\Conversation;
use App\Models\Shipment;
use App\Models\TrackingState;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Conversation channels are private and authorized via ConversationPolicy::view
| (participant OR conversation.manage). Do not authorize by shipment/driver/company alone.
|
| Tracking channels are ops-only (admin/dispatcher) via existing TrackingState /
| Vehicle / Shipment policies. Clients use GET /client/shipments/{id}/tracking
| and must not subscribe to tracking.fleet or other ops broadcast channels.
|
*/

Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    $conversation = Conversation::query()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    $user->loadMissing('role');

    return $user->can('view', $conversation);
});

Broadcast::channel('tracking.fleet', function (User $user) {
    $user->loadMissing(['role', 'company']);

    if (! $user->isFleetXInternal()) {
        return false;
    }

    return $user->can('viewAny', TrackingState::class);
});

Broadcast::channel('tracking.vehicle.{vehicleId}', function (User $user, string $vehicleId) {
    $vehicle = Vehicle::query()->find($vehicleId);

    if (! $vehicle) {
        return false;
    }

    $user->loadMissing(['role', 'company']);

    return $user->can('show', $vehicle);
});

Broadcast::channel('tracking.shipment.{shipmentId}', function (User $user, string $shipmentId) {
    $shipment = Shipment::query()->find($shipmentId);

    if (! $shipment) {
        return false;
    }

    $user->loadMissing(['role', 'company']);

    return $user->can('view', $shipment);
});
