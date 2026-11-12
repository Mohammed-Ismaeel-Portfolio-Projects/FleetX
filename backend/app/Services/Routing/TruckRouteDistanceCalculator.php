<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Application\Services\RoutePlanner;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use App\Simulation\Routing\ValueObjects\Coordinate;
use Illuminate\Support\Str;

class TruckRouteDistanceCalculator
{
    public function __construct(
        private readonly RoutePlanner $routePlanner,
    ) {}

    /**
     * Calculate truck route distance in kilometers.
     *
     * @throws RoutingProviderUnavailableException
     * @throws \InvalidArgumentException
     */
    public function calculate(
        float $pickupLatitude,
        float $pickupLongitude,
        float $deliveryLatitude,
        float $deliveryLongitude,
    ): float {
        $origin = new Coordinate($pickupLatitude, $pickupLongitude);
        $destination = new Coordinate($deliveryLatitude, $deliveryLongitude);

        $context = RoutePlanningContext::withShipment(
            shipmentId: 'pricing-estimate-' . Str::uuid(),
            vehicle: VehicleProfile::truck(
                height: 4.0,
                width: 2.5,
                length: 12.0,
                weight: 25000,
                hasTrailer: true,
            ),
            options: RoutePlanningOptions::createDefault(),
        );

        $request = RoutePlanningRequest::create(
            planningId: 'pricing-' . Str::uuid(),
            origin: $origin,
            destination: $destination,
            context: $context,
        );

        $result = $this->routePlanner->planRoute($request);

        if ($result->isFailed() || $result->route === null) {
            throw new RoutingProviderUnavailableException(
                'Route calculation failed for pricing estimate.'
            );
        }

        return $result->route->distance->toKilometers();
    }
}
