<?php

namespace App\Actions\Tracking;

use App\Core\ApiResult;
use App\Models\TrackingState;
use Illuminate\Database\Eloquent\Builder;

class QueryTrackingStatesAction
{
    public function execute(array $params = []): ApiResult
    {
        try {
            $query = TrackingState::query()->with(['assignment', 'shipment', 'vehicle', 'driver', 'trailer']);

            // Apply search filter
            if (! empty($params['search'])) {
                $search = $params['search'];
                $query->where(function (Builder $q) use ($search) {
                    $q->whereHas('vehicle', function (Builder $q) use ($search) {
                        $q->where('fleet_number', 'ilike', "%{$search}%")
                            ->orWhere('plate_number', 'ilike', "%{$search}%");
                    })
                        ->orWhereHas('driver', function (Builder $q) use ($search) {
                            $q->where('employee_number', 'ilike', "%{$search}%")
                                ->orWhere('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('shipment', function (Builder $q) use ($search) {
                            $q->where('shipment_number', 'ilike', "%{$search}%");
                        });
                });
            }

            // Apply assignment filter
            if (! empty($params['assignment_id'])) {
                $query->where('assignment_id', $params['assignment_id']);
            }

            // Apply shipment filter
            if (! empty($params['shipment_id'])) {
                $query->where('shipment_id', $params['shipment_id']);
            }

            // Apply vehicle filter
            if (! empty($params['vehicle_id'])) {
                $query->where('vehicle_id', $params['vehicle_id']);
            }

            // Apply driver filter
            if (! empty($params['driver_id'])) {
                $query->where('driver_id', $params['driver_id']);
            }

            // Apply movement status filter
            if (! empty($params['movement_status'])) {
                $query->where('movement_status', $params['movement_status']);
            }

            // Apply online status filter
            if (! empty($params['online_status'])) {
                $query->where('online_status', $params['online_status']);
            }

            // Apply signal quality filter
            if (! empty($params['signal_quality'])) {
                $query->where('signal_quality', $params['signal_quality']);
            }

            // Apply sorting
            $sortBy = $params['sort_by'] ?? 'last_update_at';
            $sortDirection = $params['sort_direction'] ?? 'desc';
            $query->orderBy($sortBy, $sortDirection);

            // Apply pagination
            $perPage = $params['per_page'] ?? 15;
            $page = $params['page'] ?? 1;

            $trackingStates = $query->paginate($perPage, ['*'], 'page', $page);

            return ApiResult::success($trackingStates, 'Tracking states retrieved successfully');
        } catch (\Throwable $e) {
            logger()->error('QueryTrackingStatesAction error: '.$e->getMessage());

            return ApiResult::error('Failed to query tracking states: '.$e->getMessage(), 500);
        }
    }
}
