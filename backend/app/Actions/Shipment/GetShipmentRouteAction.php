<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use App\Models\TrackingState;
use App\Services\TrackingSimulator\EuropeanFleetLocations;
use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Factories\VehicleProfileFactory;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Simulation\Routing\Application\Services\RouteDeviationDetector;
use App\Simulation\Routing\Application\Services\RoutePlanner;
use App\Simulation\Routing\Enums\RouteDeviationStatus;
use App\Simulation\Routing\ValueObjects\Coordinate;

/**
 * GetShipmentRouteAction
 *
 * Returns the immutable planned route for a shipment.
 * Plans via GraphHopper exactly once (first request) unless an authorized
 * regeneration is requested (force recalculate or destination change).
 */
class GetShipmentRouteAction
{
    public function __construct(
        private RoutePlanner $routePlanner,
        private EuropeanFleetLocations $fleetLocations,
        private PlannedRouteCache $plannedRouteCache,
        private RouteDeviationDetector $deviationDetector,
    ) {}

    /**
     * @param  bool  $forceRecalculate  Explicit dispatcher "Recalculate Route"
     */
    public function execute(string $shipmentId, bool $forceRecalculate = false): ApiResult
    {
        $shipment = Shipment::find($shipmentId);

        if (!$shipment) {
            return ApiResult::notFound('Shipment not found');
        }

        $cached = $this->plannedRouteCache->get($shipmentId);
        $destinationChanged = $this->destinationChanged($shipment, $cached);

        if (
            !$forceRecalculate
            && !$destinationChanged
            && $this->plannedRouteCache->hasUsableRoute($cached)
        ) {
            return ApiResult::success(
                $this->payloadFromCache($shipment, $cached),
                'Route retrieved successfully'
            );
        }

        if ($forceRecalculate || $destinationChanged) {
            $this->plannedRouteCache->invalidate($shipmentId);
        }

        $planningRequest = $this->buildPlanningRequest($shipment);
        $planningResult = $this->routePlanner->planRoute($planningRequest);

        if (!$planningResult->isSuccess()) {
            // If a frozen route still exists (race), prefer it over failing hard.
            $fallback = $this->plannedRouteCache->get($shipmentId);
            if ($this->plannedRouteCache->hasUsableRoute($fallback)) {
                return ApiResult::success(
                    $this->payloadFromCache($shipment, $fallback),
                    'Route retrieved successfully'
                );
            }

            return ApiResult::error(
                'Route calculation failed: ' . ($planningResult->diagnostics['error'] ?? 'Unknown error'),
                500
            );
        }

        $routeData = $this->transformToApiResponse($planningResult, $shipment);
        $this->persistPlannedRoute($shipment->id, $routeData, (string) $planningResult->providerName, $forceRecalculate || $destinationChanged);

        // Always serve whatever is frozen after persist (guards against double-plan races).
        $frozen = $this->plannedRouteCache->get($shipmentId);
        if ($this->plannedRouteCache->hasUsableRoute($frozen)) {
            return ApiResult::success(
                $this->payloadFromCache($shipment, $frozen),
                'Route retrieved successfully'
            );
        }

        return ApiResult::success($routeData, 'Route retrieved successfully');
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return array<string, mixed>
     */
    private function payloadFromCache(Shipment $shipment, array $cached): array
    {
        $payload = $cached['api_payload'] ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }

        $payload['metadata'] = array_merge(
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            $this->buildDeviationMetadata($shipment, $cached['coordinates'] ?? [])
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $cached
     */
    private function destinationChanged(Shipment $shipment, ?array $cached): bool
    {
        if ($cached === null || !$this->plannedRouteCache->hasUsableRoute($cached)) {
            return false;
        }

        $cachedCity = $cached['delivery_city']
            ?? ($cached['api_payload']['metadata']['delivery_city'] ?? null);

        // Legacy entries without city metadata must not force a replan.
        if ($cachedCity === null || $cachedCity === '') {
            return false;
        }

        $currentCity = (string) ($shipment->delivery_city ?: 'Paris');

        return strcasecmp((string) $cachedCity, $currentCity) !== 0;
    }

    private function buildPlanningRequest(Shipment $shipment): RoutePlanningRequest
    {
        $pickupCity = (string) ($shipment->pickup_city ?: 'Berlin');
        $deliveryCity = (string) ($shipment->delivery_city ?: 'Paris');

        $originLocation = $this->fleetLocations->resolveCity($pickupCity);
        $destinationLocation = $this->fleetLocations->resolveCity($deliveryCity);

        $origin = new Coordinate(
            (float) $originLocation['latitude'],
            (float) $originLocation['longitude']
        );
        $destination = new Coordinate(
            (float) $destinationLocation['latitude'],
            (float) $destinationLocation['longitude']
        );

        $landPath = $this->fleetLocations->buildLandPath($pickupCity, $deliveryCity);
        $waypoints = [];
        $pathCount = count($landPath);
        for ($i = 1; $i < $pathCount - 1; $i++) {
            $waypoints[] = new Coordinate(
                (float) $landPath[$i]['latitude'],
                (float) $landPath[$i]['longitude']
            );
        }

        $vehicleProfile = $this->resolveAssignedVehicleProfile($shipment);
        $planningOptions = RoutePlanningOptions::createDefault();
        $planningContext = RoutePlanningContext::withShipment(
            shipmentId: $shipment->id,
            vehicle: $vehicleProfile,
            options: $planningOptions
        );

        return RoutePlanningRequest::create(
            planningId: 'route-' . $shipment->id,
            origin: $origin,
            destination: $destination,
            context: $planningContext,
            waypoints: $waypoints
        );
    }

    private function resolveAssignedVehicleProfile(Shipment $shipment): VehicleProfile
    {
        $assignment = $shipment->assignments()
            ->active()
            ->with(['vehicle', 'trailer'])
            ->latest('created_at')
            ->first();

        return VehicleProfileFactory::fromAssignment($assignment);
    }

    private function transformToApiResponse($planningResult, Shipment $shipment): array
    {
        $route = $planningResult->route;

        if (!$route) {
            return [
                'route' => null,
                'metadata' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_number,
                    'error' => 'No route calculated',
                    'route_status' => RouteDeviationStatus::ON_ROUTE->value,
                    'deviation_distance' => 0,
                    'rerouting_available' => (bool) config('routing.deviation.auto_reroute', true),
                ],
            ];
        }

        $eta = now()->addSeconds((int) $route->duration->toSeconds())->format('Y-m-d H:i:s');
        $polyline = $route->geometry->getPolyline();
        $coordinates = array_map(
            fn ($coordinate) => $coordinate->toArray(),
            $polyline->getCoordinates()
        );

        $deviationMeta = $this->buildDeviationMetadata($shipment, $coordinates);

        return [
            'route' => [
                'id' => $route->id,
                'origin' => $route->origin->toArray(),
                'destination' => $route->destination->toArray(),
                'distance' => $route->distance->toArray(),
                'duration' => $route->duration->toArray(),
                'eta' => $eta,
                'geometry' => [
                    'encoded' => $polyline->encoded,
                    'coordinates' => $coordinates,
                    'bounds' => $route->geometry->getBounds(),
                ],
                'legs' => array_map(fn ($leg) => $leg->toArray(), $route->legs),
                'steps' => $this->extractStepsFromLegs($route->legs),
                'waypoints' => array_map(fn ($wp) => $wp->toArray(), $route->waypoints),
                'status' => $route->status->value,
                'travel_mode' => $route->travelMode->value,
                'provider' => $route->provider,
            ],
            'metadata' => array_merge([
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'pickup_city' => $shipment->pickup_city,
                'delivery_city' => $shipment->delivery_city,
                'provider_name' => $planningResult->providerName,
                'execution_time_ms' => $planningResult->executionTime,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ], $deviationMeta),
        ];
    }

    /**
     * @param array<int, array{latitude?: float, longitude?: float}> $coordinates
     * @return array{route_status: string, deviation_distance: float, rerouting_available: bool}
     */
    private function buildDeviationMetadata(Shipment $shipment, array $coordinates): array
    {
        $reroutingAvailable = (bool) config('routing.deviation.auto_reroute', true);
        $default = [
            'route_status' => RouteDeviationStatus::ON_ROUTE->value,
            'deviation_distance' => 0.0,
            'rerouting_available' => $reroutingAvailable,
        ];

        if (!config('routing.deviation.enabled', true) || $coordinates === []) {
            return $default;
        }

        $tracking = TrackingState::query()
            ->where('shipment_id', $shipment->id)
            ->latest('last_update_at')
            ->first();

        if ($tracking === null) {
            return $default;
        }

        $result = $this->deviationDetector->detect(
            latitude: (float) $tracking->latitude,
            longitude: (float) $tracking->longitude,
            routeCoordinates: $coordinates,
            speedKmh: (float) $tracking->speed,
            shipmentId: $shipment->id,
        );

        return [
            'route_status' => $result->routeStatus->value,
            'deviation_distance' => round($result->deviationDistanceMeters, 2),
            'rerouting_available' => $reroutingAvailable,
        ];
    }

    private function persistPlannedRoute(
        string $shipmentId,
        array $routeData,
        string $provider,
        bool $replace,
    ): void {
        $route = $routeData['route'] ?? null;
        if (!is_array($route) || empty($route['geometry']['coordinates'])) {
            return;
        }

        $args = [
            $shipmentId,
            $route['geometry']['coordinates'],
            $route['destination'] ?? [],
            $route['origin'] ?? [],
            $routeData,
            $provider,
            false,
        ];

        if ($replace) {
            $this->plannedRouteCache->replace(...$args);

            return;
        }

        $this->plannedRouteCache->storeIfAbsent(...$args);
    }

    private function extractStepsFromLegs(array $legs): array
    {
        $steps = [];
        foreach ($legs as $leg) {
            foreach ($leg->steps as $step) {
                $steps[] = $step->toArray();
            }
        }

        return $steps;
    }
}
