<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;

class GetShipmentAction
{
    public function execute(string $shipmentId): ApiResult
    {
        $shipment = Shipment::with([
            'customerCompany',
            'status',
            'shipmentOrder.status',
            'statusHistory.fromStatus',
            'statusHistory.toStatus',
            'statusHistory.changedBy',
            'activeAssignment.status',
            'activeAssignment.driver',
            'activeAssignment.vehicle',
            'activeAssignment.trailer',
            'latestAssignment.status',
            'latestAssignment.driver',
            'latestAssignment.vehicle',
            'latestAssignment.trailer',
            'trackingLogs' => fn ($query) => $query->orderByDesc('recorded_at')->limit(50),
            'creator',
            'updater',
            'finalPriceBreakdown.variances',
            'finalPriceBreakdown.submissionPriceBreakdown',
            'finalPriceBreakdown.operationalActuals',
            'finalPriceBreakdown.pricingPolicy',
        ])->find($shipmentId);

        if (!$shipment) {
            return ApiResult::notFound('Shipment not found');
        }

        return ApiResult::success($shipment, 'Shipment retrieved successfully');
    }
}
