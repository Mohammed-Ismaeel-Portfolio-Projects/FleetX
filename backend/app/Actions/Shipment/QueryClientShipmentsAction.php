<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company-scoped shipment list for authenticated client users (C2.6-C2).
 * Company id must come from the authenticated user — never from request input.
 */
class QueryClientShipmentsAction
{
    public function execute(User $user, array $params): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $query = Shipment::query()
            ->forCustomer($companyId)
            ->with(['status', 'customerCompany', 'finalPriceBreakdown']);

        if (! empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('shipment_number', 'ilike', "%{$search}%")
                    ->orWhere('reference_number', 'ilike', "%{$search}%")
                    ->orWhere('pickup_city', 'ilike', "%{$search}%")
                    ->orWhere('pickup_country', 'ilike', "%{$search}%")
                    ->orWhere('delivery_city', 'ilike', "%{$search}%")
                    ->orWhere('delivery_country', 'ilike', "%{$search}%");
            });
        }

        if (! empty($params['status_id'])) {
            $query->where('status_id', $params['status_id']);
        } elseif (! empty($params['status'])) {
            $statusName = $params['status'];
            $query->whereHas('status', function (Builder $q) use ($statusName) {
                $q->where('type', 'shipment')
                    ->where('name', $statusName);
            });
        }

        if (! empty($params['priority'])) {
            $query->where('priority', $params['priority']);
        }

        if (! empty($params['country'])) {
            $country = $params['country'];
            $query->where(function (Builder $q) use ($country) {
                $q->where('pickup_country', $country)
                    ->orWhere('delivery_country', $country);
            });
        }

        if (! empty($params['pickup_from'])) {
            $query->where('planned_pickup_date', '>=', $params['pickup_from']);
        }

        if (! empty($params['pickup_to'])) {
            $query->where('planned_pickup_date', '<=', $params['pickup_to']);
        }

        if (! empty($params['delivery_from'])) {
            $query->where('planned_delivery_date', '>=', $params['delivery_from']);
        }

        if (! empty($params['delivery_to'])) {
            $query->where('planned_delivery_date', '<=', $params['delivery_to']);
        }

        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortDirection = $params['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        $shipments = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($shipments, 'Shipments retrieved successfully');
    }
}
