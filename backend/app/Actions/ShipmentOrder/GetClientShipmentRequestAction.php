<?php

namespace App\Actions\ShipmentOrder;

use App\Core\ApiResult;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Support\ShipmentOrderStatus;

/**
 * Company-scoped Shipment Request show for authenticated client users (C2.9-A3.4.1 / C5.3).
 * Company id must come from the authenticated user — never from request input.
 */
class GetClientShipmentRequestAction
{
    public function execute(User $user, string $shipmentOrderId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $order = ShipmentOrder::query()
            ->forCustomer($companyId)
            ->with(['status', 'convertedShipment.status', 'priceBreakdown'])
            ->find($shipmentOrderId);

        if ($order === null) {
            return ApiResult::notFound('Shipment request not found');
        }

        $statusName = $order->status?->name;
        if ($statusName === ShipmentOrderStatus::CHANGES_REQUESTED
            || $statusName === ShipmentOrderStatus::REJECTED) {
            $order->load([
                'statusHistory' => function ($query) use ($order) {
                    $query->reorder()
                        ->where('to_status_id', $order->status_id)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id');
                },
            ]);
        }

        return ApiResult::success($order, 'Shipment request retrieved successfully');
    }
}
