<?php

namespace App\Actions\ShipmentOrder;

use App\Core\ApiResult;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company-scoped Shipment Request list for authenticated client users (C2.9-A3.4.1 / C5.4).
 * Company id must come from the authenticated user — never from request input.
 */
class QueryClientShipmentRequestsAction
{
    public function execute(User $user, array $params): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $query = ShipmentOrder::query()
            ->forCustomer($companyId)
            ->with(['status', 'convertedShipment.status', 'priceBreakdown']);

        if (! empty($params['status_id'])) {
            $query->where('status_id', $params['status_id']);
        } elseif (! empty($params['status'])) {
            $statusName = $params['status'];
            $query->whereHas('status', function (Builder $q) use ($statusName) {
                $q->where('type', ShipmentOrderStatus::TYPE)
                    ->where('name', $statusName);
            });
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        $orders = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($orders, 'Shipment requests retrieved successfully');
    }
}
