<?php

namespace App\Actions\ShipmentOrder;

use App\Core\ApiResult;
use App\Models\ShipmentOrder;
use App\Support\ShipmentOrderStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global Shipment Request list for Dispatcher/Admin (C2.9-B2.1).
 * Cross-customer operational visibility — not company-scoped.
 */
class QueryShipmentRequestsAction
{
    public function execute(array $params): ApiResult
    {
        $query = ShipmentOrder::query()
            ->with(['status', 'customerCompany', 'priceBreakdown']);

        if (! empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('request_number', 'ilike', "%{$search}%")
                    ->orWhere('reference_number', 'ilike', "%{$search}%")
                    ->orWhereHas('customerCompany', function (Builder $q) use ($search) {
                        $q->where('company_name', 'ilike', "%{$search}%")
                            ->orWhere('company_code', 'ilike', "%{$search}%");
                    });
            });
        }

        if (! empty($params['customer_company_id'])) {
            $query->where('customer_company_id', $params['customer_company_id']);
        }

        if (! empty($params['status_id'])) {
            $query->where('status_id', $params['status_id']);
        } elseif (! empty($params['status'])) {
            $query->whereHas('status', function (Builder $q) use ($params) {
                $q->where('type', ShipmentOrderStatus::TYPE)
                    ->whereRaw('LOWER(name) = LOWER(?)', [$params['status']]);
            });
        }

        $hasExplicitStatusFilter = ! empty($params['status_id']) || (! empty($params['status']) && strtolower((string) $params['status']) === 'converted');
        $shouldExcludeConverted = isset($params['exclude_converted'])
            ? (bool) $params['exclude_converted']
            : ! $hasExplicitStatusFilter;

        if ($shouldExcludeConverted) {
            $query->whereNull('converted_shipment_id')
                ->whereDoesntHave('status', function (Builder $q) {
                    $q->where('type', ShipmentOrderStatus::TYPE)
                        ->where('name', ShipmentOrderStatus::CONVERTED);
                });
        }

        if (! empty($params['requested_pickup_date_from'])) {
            $query->where('requested_pickup_date', '>=', $params['requested_pickup_date_from']);
        }

        if (! empty($params['requested_pickup_date_to'])) {
            $query->where('requested_pickup_date', '<=', $params['requested_pickup_date_to']);
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        $perPage = min((int) ($params['per_page'] ?? 15), 100);
        $page = (int) ($params['page'] ?? 1);

        $orders = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($orders, 'Shipment requests retrieved successfully');
    }
}
