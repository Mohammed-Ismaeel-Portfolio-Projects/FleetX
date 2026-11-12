<?php

namespace App\Actions\Dispatcher;

use App\Models\ShipmentOrder;
use App\Models\TrackingSession;

class GetTrackingSessionForShipmentAction
{
    public function execute(string|ShipmentOrder $shipmentOrder): ?TrackingSession
    {
        $orderId = $shipmentOrder instanceof ShipmentOrder ? $shipmentOrder->id : $shipmentOrder;

        $session = TrackingSession::where('shipment_order_id', $orderId)
            ->orWhere('shipment_id', $orderId)
            ->latest('created_at')
            ->first();

        if (! $session) {
            return null;
        }

        $session->loadMissing([
            'shipmentOrder',
            'assignment.trailer',
            'driver',
            'vehicle',
        ]);

        return $session;
    }
}
