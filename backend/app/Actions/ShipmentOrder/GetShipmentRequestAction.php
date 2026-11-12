<?php

namespace App\Actions\ShipmentOrder;

use App\Core\ApiResult;
use App\Models\ShipmentOrder;

/**
 * Global Shipment Request show for Dispatcher/Admin (C2.9-B2.1).
 */
class GetShipmentRequestAction
{
    public function __construct(
        private readonly CalculateShipmentOrderPlannedRouteAction $calculateRouteAction,
    ) {}

    public function execute(string $shipmentOrderId): ApiResult
    {
        $order = ShipmentOrder::query()
            ->with([
                'status',
                'customerCompany',
                'submittedBy',
                'convertedShipment.status',
                'statusHistory.fromStatus',
                'statusHistory.toStatus',
                'statusHistory.changedBy',
                'priceBreakdown.pricingPolicy',
            ])
            ->find($shipmentOrderId);

        if ($order === null) {
            return ApiResult::notFound('Shipment request not found');
        }

        $order = $this->calculateRouteAction->execute($order);

        return ApiResult::success($order, 'Shipment request retrieved successfully');
    }
}
