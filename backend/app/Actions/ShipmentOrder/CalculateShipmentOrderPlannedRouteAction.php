<?php

namespace App\Actions\ShipmentOrder;

use App\Models\ShipmentOrder;
use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Application\Services\RoutePlanner;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Support\ClientShipmentRequestCoordinateRules;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalculateShipmentOrderPlannedRouteAction
{
    public function __construct(
        private readonly RoutePlanner $routePlanner,
    ) {}

    public function execute(ShipmentOrder $order, bool $forceRecalculate = false): ShipmentOrder
    {
        $pickupLat = ClientShipmentRequestCoordinateRules::toFloat($order->pickup_latitude);
        $pickupLng = ClientShipmentRequestCoordinateRules::toFloat($order->pickup_longitude);
        $deliveryLat = ClientShipmentRequestCoordinateRules::toFloat($order->delivery_latitude);
        $deliveryLng = ClientShipmentRequestCoordinateRules::toFloat($order->delivery_longitude);

        if ($pickupLat === null || $pickupLng === null || $deliveryLat === null || $deliveryLng === null) {
            return $order;
        }

        // Check route freshness & reuse
        if (! $forceRecalculate && $order->planned_route_status === 'AVAILABLE' && ! empty($order->planned_route_geometry)) {
            $originLat = ClientShipmentRequestCoordinateRules::toFloat($order->planned_route_origin_latitude);
            $originLng = ClientShipmentRequestCoordinateRules::toFloat($order->planned_route_origin_longitude);
            $destLat = ClientShipmentRequestCoordinateRules::toFloat($order->planned_route_destination_latitude);
            $destLng = ClientShipmentRequestCoordinateRules::toFloat($order->planned_route_destination_longitude);

            if ($originLat === $pickupLat && $originLng === $pickupLng && $destLat === $deliveryLat && $destLng === $deliveryLng) {
                return $order;
            }
        }

        try {
            $origin = new Coordinate($pickupLat, $pickupLng);
            $destination = new Coordinate($deliveryLat, $deliveryLng);

            $context = RoutePlanningContext::withShipment(
                shipmentId: $order->id,
                vehicle: VehicleProfile::truck(
                    height: 4.0,
                    width: 2.5,
                    length: 12.0,
                    weight: 25000,
                    hasTrailer: true
                ),
                options: RoutePlanningOptions::createDefault()
            );

            $request = RoutePlanningRequest::create(
                planningId: 'planned-route-' . Str::uuid(),
                origin: $origin,
                destination: $destination,
                context: $context
            );

            $result = $this->routePlanner->planRoute($request);

            if ($result->isFailed() || $result->route === null) {
                $order->update([
                    'planned_route_status' => 'UNAVAILABLE',
                    'planned_route_origin_latitude' => $pickupLat,
                    'planned_route_origin_longitude' => $pickupLng,
                    'planned_route_destination_latitude' => $deliveryLat,
                    'planned_route_destination_longitude' => $deliveryLng,
                    'planned_route_calculated_at' => now(),
                ]);
                return $order;
            }

            $route = $result->route;
            $geometryCoords = array_map(
                fn (Coordinate $coord) => [$coord->latitude, $coord->longitude],
                $route->geometry->polyline->getCoordinates()
            );

            $distanceMeters = (int) round($route->distance->toMeters());
            $durationSeconds = (int) round($route->duration->toSeconds());

            $order->update([
                'planned_distance_meters' => $distanceMeters,
                'planned_duration_seconds' => $durationSeconds,
                'planned_route_geometry' => $geometryCoords,
                'planned_route_origin_latitude' => $pickupLat,
                'planned_route_origin_longitude' => $pickupLng,
                'planned_route_destination_latitude' => $deliveryLat,
                'planned_route_destination_longitude' => $deliveryLng,
                'planned_route_status' => 'AVAILABLE',
                'planned_route_calculated_at' => now(),
            ]);

            return $order;
        } catch (\Throwable $e) {
            Log::warning('Planned route calculation failed for ShipmentOrder', [
                'shipment_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->update([
                'planned_route_status' => 'UNAVAILABLE',
                'planned_route_origin_latitude' => $pickupLat,
                'planned_route_origin_longitude' => $pickupLng,
                'planned_route_destination_latitude' => $deliveryLat,
                'planned_route_destination_longitude' => $deliveryLng,
                'planned_route_calculated_at' => now(),
            ]);

            return $order;
        }
    }
}
