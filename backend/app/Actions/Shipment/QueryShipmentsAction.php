<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;

class QueryShipmentsAction
{
    public function execute(array $params): ApiResult
    {
        // Do not eager-load `route`: routes.shipment_id is bigint while shipments.id is UUID,
        // so the join fails and GET /shipments 500s. ShipmentResource does not expose route on list.
        $query = Shipment::with([
            'customerCompany',
            'status',
            'activeAssignment.driver',
            'activeAssignment.vehicle',
            'activeAssignment.trailer',
            'latestAssignment.driver',
            'latestAssignment.vehicle',
            'latestAssignment.trailer',
            'finalPriceBreakdown',
        ]);

        // Apply search filter
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('shipment_number', 'ilike', "%{$search}%")
                  ->orWhere('reference_number', 'ilike', "%{$search}%")
                  ->orWhereHas('customerCompany', function (Builder $q) use ($search) {
                      $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('company_code', 'ilike', "%{$search}%");
                  });
            });
        }

        // Apply customer filter
        if (!empty($params['customer_company_id'])) {
            $query->where('customer_company_id', $params['customer_company_id']);
        }

        // Apply executing partner carrier filter
        if (!empty($params['executing_company_id'])) {
            $query->where('executing_company_id', $params['executing_company_id']);
        }

        // Apply status filter (id or official status name)
        if (!empty($params['status_id'])) {
            $query->where('status_id', $params['status_id']);
        } elseif (!empty($params['status'])) {
            $query->whereHas('status', function (Builder $q) use ($params) {
                $q->where('type', 'shipment')
                    ->where('name', $params['status']);
            });
        }

        // Apply priority filter
        if (!empty($params['priority'])) {
            $query->where('priority', $params['priority']);
        }

        // Apply country filter (pickup or delivery) — grouped to avoid bypassing other filters
        if (!empty($params['country'])) {
            $country = $params['country'];
            $query->where(function (Builder $q) use ($country) {
                $q->where('pickup_country', $country)
                    ->orWhere('delivery_country', $country);
            });
        }

        // Apply pickup date range filter
        if (!empty($params['pickup_from'])) {
            $query->where('planned_pickup_date', '>=', $params['pickup_from']);
        }

        if (!empty($params['pickup_to'])) {
            $query->where('planned_pickup_date', '<=', $params['pickup_to']);
        }

        // Apply delivery date range filter
        if (!empty($params['delivery_from'])) {
            $query->where('planned_delivery_date', '>=', $params['delivery_from']);
        }

        if (!empty($params['delivery_to'])) {
            $query->where('planned_delivery_date', '<=', $params['delivery_to']);
        }

        // Apply sorting
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortDirection = $params['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        $shipments = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($shipments, 'Shipments retrieved successfully');
    }
}
