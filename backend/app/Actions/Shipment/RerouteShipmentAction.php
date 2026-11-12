<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Events\Tracking\RouteUpdatedBroadcasted;
use App\Models\Shipment;
use App\Services\TrackingSimulator\EuropeanFleetLocations;
use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Factories\VehicleProfileFactory;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Simulation\Routing\Application\Services\RoutePlanner;
use App\Simulation\Routing\ValueObjects\Coordinate;

/**
 * RerouteShipmentAction
 *
 * Recalculates a truck-compatible route from the vehicle's current GPS
 * position to the shipment destination via the existing Routing Pipeline.
 */
class RerouteShipmentAction
{
    public function __construct(
        private RoutePlanner $routePlanner,
        private EuropeanFleetLocations $fleetLocations,
        private PlannedRouteCache $plannedRouteCache,
    ) {}

    public function execute(string $shipmentId, float $latitude, float $longitude): ApiResult
    {
        $shipment = Shipment::find($shipmentId);

        if (!$shipment) {
            return ApiResult::notFound('Shipment not found');
        }

        $assignment = $shipment->assignments()
            ->active()
            ->with(['vehicle', 'trailer'])
            ->latest('created_at')
            ->first();

        $vehicleProfile = VehicleProfileFactory::fromAssignment($assignment);

        $deliveryCity = (string) ($shipment->delivery_city ?: 'Paris');
        $destinationLocation = $this->fleetLocations->resolveCity($deliveryCity);

        $origin = new Coordinate($latitude, $longitude);
        $destination = new Coordinate(
            (float) $destinationLocation['latitude'],
            (float) $destinationLocation['longitude']
        );

        $planningOptions = RoutePlanningOptions::createDefault();
        $planningContext = RoutePlanningContext::withShipment(
            shipmentId: $shipment->id,
            vehicle: $vehicleProfile,
            options: $planningOptions
        );

        $planningRequest = RoutePlanningRequest::create(
            planningId: 'reroute-' . $shipment->id . '-' . now()->timestamp,
            origin: $origin,
            destination: $destination,
            context: $planningContext,
            waypoints: []
        );

        $planningResult = $this->routePlanner->planRoute($planningRequest);

        if (!$planningResult->isSuccess() || $planningResult->route === null) {
            return ApiResult::error(
                'Reroute calculation failed: ' . ($planningResult->diagnostics['error'] ?? 'Unknown error'),
                500
            );
        }

        $route = $planningResult->route;
        $polyline = $route->geometry->getPolyline();
        $coordinates = array_map(
            fn ($coordinate) => $coordinate->toArray(),
            $polyline->getCoordinates()
        );

        $eta = now()->addSeconds((int) $route->duration->toSeconds())->format('Y-m-d H:i:s');

        $apiPayload = [
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
                'steps' => [],
                'waypoints' => array_map(fn ($wp) => $wp->toArray(), $route->waypoints),
                'status' => $route->status->value,
                'travel_mode' => $route->travelMode->value,
                'provider' => $route->provider,
            ],
            'metadata' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'pickup_city' => $shipment->pickup_city,
                'delivery_city' => $shipment->delivery_city,
                'provider_name' => $planningResult->providerName,
                'execution_time_ms' => $planningResult->executionTime,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'is_reroute' => true,
                'route_status' => 'ON_ROUTE',
                'deviation_distance' => 0,
                'rerouting_available' => (bool) config('routing.deviation.auto_reroute', true),
            ],
        ];

        // Authorized regeneration: invalidate old planned/geometry/progress, store exactly one replacement.
        $this->plannedRouteCache->replace(
            shipmentId: $shipment->id,
            coordinates: $coordinates,
            destination: $destination->toArray(),
            origin: $origin->toArray(),
            apiPayload: $apiPayload,
            provider: (string) $planningResult->providerName,
            isReroute: true,
        );
        $this->plannedRouteCache->markRerouted($shipment->id);

        RouteUpdatedBroadcasted::dispatch(
            shipmentId: (string) $shipment->id,
            vehicleId: $assignment ? (string) $assignment->vehicle_id : null,
            assignmentId: $assignment ? (string) $assignment->id : null,
            routePayload: $apiPayload,
            timestamp: now()->toIso8601String(),
        );

        return ApiResult::success($apiPayload, 'Shipment rerouted successfully');
    }
}
