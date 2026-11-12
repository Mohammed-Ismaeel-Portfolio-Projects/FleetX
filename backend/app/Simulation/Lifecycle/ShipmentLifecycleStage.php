<?php

namespace App\Simulation\Lifecycle;

/**
 * Supported autonomous shipment lifecycle stages.
 */
enum ShipmentLifecycleStage: string
{
    case Created = 'Created';
    case Assigned = 'Assigned';
    case Accepted = 'Accepted';
    case EnRoutePickup = 'EnRoutePickup';
    case ArrivedPickup = 'ArrivedPickup';
    case Loading = 'Loading';
    case Loaded = 'Loaded';
    case Departed = 'Departed';
    case EnRouteDelivery = 'EnRouteDelivery';
    case ApproachingRestStop = 'ApproachingRestStop';
    case Resting = 'Resting';
    case ArrivedDelivery = 'ArrivedDelivery';
    case Unloading = 'Unloading';
    case Delivered = 'Delivered';
    case Completed = 'Completed';

    /**
     * Legacy mapping of operational stage → historical Status.name values.
     *
     * @deprecated C2.9-C2.8 Step 2 — must NOT be used to write Shipment.status_id.
     * Canonical writes go through ShipmentLifecycleTransitionAdapter → TransitionShipmentStatusAction.
     * Retained only for documentation / bootstrap reverse-hints.
     */
    public function toDatabaseStatusName(): string
    {
        return match ($this) {
            self::Created => 'Draft',
            self::Assigned => 'Assigned',
            self::Accepted, self::EnRoutePickup, self::ArrivedPickup, self::Loading => 'Ready For Pickup',
            self::Loaded => 'Picked Up',
            self::Departed,
            self::EnRouteDelivery,
            self::ApproachingRestStop,
            self::Resting => 'In Transit',
            self::ArrivedDelivery, self::Unloading => 'Out For Delivery',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed;
    }
}
