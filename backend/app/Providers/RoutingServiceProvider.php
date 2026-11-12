<?php

namespace App\Providers;

use App\Simulation\Routing\Application\Resolvers\ProviderResolver;
use App\Simulation\Routing\Application\Services\ResponseMapperResolver;
use App\Simulation\Routing\Infrastructure\Providers\GraphHopper\GraphHopperProvider;
use App\Simulation\Routing\Infrastructure\Providers\OSRM\OSRMProvider;
use App\Simulation\Routing\Infrastructure\Providers\Simulator\SimulatorProvider;
use App\Simulation\Routing\Infrastructure\ResponseMappers\GraphHopperResponseMapper;
use App\Simulation\Routing\Infrastructure\ResponseMappers\OSRMResponseMapper;
use App\Simulation\Routing\Infrastructure\ResponseMappers\SimulatorResponseMapper;
use Illuminate\Support\ServiceProvider;

/**
 * RoutingServiceProvider
 *
 * Registers routing providers and their mappers.
 */
class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderResolver::class, function ($app) {
            $resolver = new ProviderResolver();

            // Primary production provider
            $graphHopperProvider = new GraphHopperProvider();
            $resolver->registerProvider(
                $graphHopperProvider,
                priority: config('routing.provider_priority.graphhopper', 200),
                capabilities: [
                    'supported_modes' => ['driving', 'cycling', 'walking'],
                    'supported_vehicle_types' => ['car', 'truck', 'motorcycle', 'bicycle', 'pedestrian'],
                    'supports_large_vehicles' => true,
                ]
            );

            // Optional secondary real provider
            $osrmProvider = new OSRMProvider();
            $resolver->registerProvider(
                $osrmProvider,
                priority: config('routing.provider_priority.osrm', 150),
                capabilities: [
                    'supported_modes' => ['driving', 'cycling', 'walking'],
                    'supported_vehicle_types' => ['car', 'truck', 'motorcycle', 'bicycle', 'pedestrian'],
                    'supports_large_vehicles' => true,
                ]
            );

            // Development / offline fallback
            $simulatorProvider = new SimulatorProvider();
            $resolver->registerProvider(
                $simulatorProvider,
                priority: config('routing.provider_priority.simulator', 50),
                capabilities: [
                    'supported_modes' => ['driving', 'walking', 'cycling'],
                    'supported_vehicle_types' => ['car', 'truck', 'motorcycle', 'bicycle', 'pedestrian'],
                    'supports_large_vehicles' => true,
                ]
            );

            return $resolver;
        });

        $this->app->singleton(ResponseMapperResolver::class, function ($app) {
            $resolver = new ResponseMapperResolver();

            $resolver->registerMapper('graphhopper', new GraphHopperResponseMapper());
            $resolver->registerMapper('osrm', new OSRMResponseMapper());
            $resolver->registerMapper('simulator', new SimulatorResponseMapper());

            return $resolver;
        });
    }

    public function boot(): void
    {
        //
    }
}
