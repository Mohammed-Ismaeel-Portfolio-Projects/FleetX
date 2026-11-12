<?php

namespace App\Policies;

use App\Models\ShipmentOrder;
use App\Models\User;

/**
 * Ops authorization for ShipmentOrder (C2.9-B2.1 read, C2.9-B2.2 review transitions).
 */
class ShipmentOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shipmentOrder.viewAny');
    }

    public function view(User $user, ShipmentOrder $shipmentOrder): bool
    {
        return $user->can('shipmentOrder.view');
    }

    public function startReview(User $user, ShipmentOrder $shipmentOrder): bool
    {
        return $user->can('shipmentOrder.startReview');
    }

    public function requestChanges(User $user, ShipmentOrder $shipmentOrder): bool
    {
        return $user->can('shipmentOrder.requestChanges');
    }

    public function approve(User $user, ShipmentOrder $shipmentOrder): bool
    {
        return $user->can('shipmentOrder.approve');
    }

    public function reject(User $user, ShipmentOrder $shipmentOrder): bool
    {
        return $user->can('shipmentOrder.reject');
    }

    public function convert(User $user, ShipmentOrder $shipmentOrder): bool
    {
        return $user->can('shipmentOrder.convert');
    }
}
