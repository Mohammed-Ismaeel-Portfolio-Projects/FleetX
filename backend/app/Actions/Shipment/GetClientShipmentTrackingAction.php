<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\TrackingState;
use App\Models\User;
use App\Support\Client\ClientShipmentEndpointCoordinates;
use App\Support\Client\ClientShipmentLocationFreshness;
use App\Support\Client\ClientShipmentPlannedRoute;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Company-scoped shipment tracking for authenticated client users
 * (C2.7-C / C4.2-A3.2 / C4.2-A3.3 / C5.5-C2.2 / C5.5-C2.3 / C5.5-C2.4).
 * Company id must come from the authenticated user — never from request input.
 * Does not reuse ops tracking logs/states/live-fleet or GetShipmentRouteAction.
 */
class GetClientShipmentTrackingAction
{
    public function __construct(
        private readonly ClientShipmentEndpointCoordinates $endpointCoordinates,
        private readonly ClientShipmentPlannedRoute $plannedRoute,
    ) {}

    /**
     * @return ApiResult Shipment model on success (milestones + position + endpoint coords + freshness + route).
     */
    public function execute(User $user, string $shipmentId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        // SoftDeletes default + forCustomer scope: foreign / deleted / missing → null → 404.
        $shipment = Shipment::query()
            ->forCustomer($companyId)
            ->with(['status', 'customerCompany'])
            ->find($shipmentId);

        if ($shipment === null) {
            return ApiResult::notFound('Shipment not found');
        }

        // Tracking projection only after company-scoped shipment resolution succeeds.
        $lastKnownPosition = $this->resolveLastKnownPosition($shipment);
        $shipment->setAttribute('last_known_position', $lastKnownPosition);
        $shipment->setAttribute(
            'location_freshness',
            ClientShipmentLocationFreshness::fromLastKnownPosition($lastKnownPosition)
        );

        // Planned geometry resolution (bootstraps PlannedRouteCache if absent).
        $clientRoute = $this->plannedRoute->resolveForShipment((string) $shipment->id);
        $shipment->setAttribute('client_route', $clientRoute);

        // Hub endpoint coordinates (reads origin/destination from PlannedRouteCache or fallback).
        $endpoints = $this->endpointCoordinates->resolveForShipment(
            (string) $shipment->id,
            $shipment->pickup_city,
            $shipment->delivery_city
        );
        $shipment->setAttribute('origin_coordinates', $endpoints['origin']);
        $shipment->setAttribute('destination_coordinates', $endpoints['destination']);

        return ApiResult::success($shipment, 'Shipment tracking retrieved successfully');
    }

    /**
     * Priority: active-assignment TrackingState → latest shipment TrackingState → latest TrackingLog → null.
     * Invalid candidates fail closed. Within Priority 2/3, skip invalid rows and keep searching
     * by recency before falling through to the next priority.
     *
     * @return array{latitude: float, longitude: float, recorded_at: string}|null
     */
    private function resolveLastKnownPosition(Shipment $shipment): ?array
    {
        // SoftDeletes on Assignment: soft-deleted rows are excluded from activeAssignment().
        $activeAssignment = $shipment->activeAssignment;

        if ($activeAssignment !== null) {
            $activeState = TrackingState::query()
                ->where('assignment_id', $activeAssignment->id)
                ->where('shipment_id', $shipment->id)
                ->first();

            $projected = $this->projectTrackingState($activeState);
            if ($projected !== null) {
                return $projected;
            }
        }

        $stateCandidates = TrackingState::query()
            ->where('shipment_id', $shipment->id)
            ->orderByDesc('last_update_at')
            ->orderByDesc('id')
            ->cursor();

        foreach ($stateCandidates as $stateCandidate) {
            $projected = $this->projectTrackingState($stateCandidate);
            if ($projected !== null) {
                return $projected;
            }
        }

        $logCandidates = $shipment->trackingLogs()
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->cursor();

        foreach ($logCandidates as $logCandidate) {
            $projected = $this->projectTrackingLog($logCandidate);
            if ($projected !== null) {
                return $projected;
            }
        }

        return null;
    }

    /**
     * @return array{latitude: float, longitude: float, recorded_at: string}|null
     */
    private function projectTrackingState(?TrackingState $state): ?array
    {
        if ($state === null) {
            return null;
        }

        return $this->projectPosition(
            $state->latitude,
            $state->longitude,
            $state->last_update_at
        );
    }

    /**
     * @return array{latitude: float, longitude: float, recorded_at: string}|null
     */
    private function projectTrackingLog(?TrackingLog $log): ?array
    {
        if ($log === null) {
            return null;
        }

        return $this->projectPosition(
            $log->latitude,
            $log->longitude,
            $log->recorded_at
        );
    }

    /**
     * Fail-closed three-field projection. Never returns a partial object.
     *
     * @return array{latitude: float, longitude: float, recorded_at: string}|null
     */
    private function projectPosition(mixed $latitude, mixed $longitude, mixed $recordedAt): ?array
    {
        $lat = $this->normalizeCoordinate($latitude);
        $lng = $this->normalizeCoordinate($longitude);
        $at = $this->normalizeRecordedAt($recordedAt);

        if ($lat === null || $lng === null || $at === null) {
            return null;
        }

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'recorded_at' => $at,
        ];
    }

    private function normalizeCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && ! is_numeric($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        if (! is_finite($float)) {
            return null;
        }

        return $float;
    }

    private function normalizeRecordedAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            }

            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }

            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
