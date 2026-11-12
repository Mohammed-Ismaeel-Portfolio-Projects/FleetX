<?php

namespace App\Simulation\Domain\Route\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Contracts\EventBusInterface;
use App\Simulation\Domain\Route\Services\RouteAdvancer;
use App\Simulation\Domain\Route\Services\RouteInterpolator;
use App\Simulation\Domain\Route\Services\RouteDistanceCalculator;
use App\Simulation\Domain\Route\Services\RouteCompletionDetector;
use App\Simulation\Domain\Route\Configuration\RouteConfiguration;
use App\Simulation\Domain\Route\Events\RouteStarted;
use App\Simulation\Domain\Route\Events\WaypointReached;
use App\Simulation\Domain\Route\Events\RouteSegmentChanged;
use App\Simulation\Domain\Route\Events\RouteCompleted;
use App\Simulation\Domain\Route\Models\Route;
use App\Simulation\Domain\Route\Models\RouteProgress;
use App\Simulation\Domain\Route\Models\RoutePosition;
use App\Simulation\Domain\Route\DTOs\RoutePositionOutput;
use App\Simulation\Domain\Vehicle\Repositories\VehicleRepository;
use App\Simulation\Core\SimulationClock;

/**
 * RouteModule - Route Simulation Orchestrator.
 * Manages logical vehicle movement along predefined routes.
 */
class RouteModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;
    private VehicleRepository $vehicleRepository;
    private SimulationClock $clock;
    private RouteConfiguration $configuration;
    private EventBusInterface $eventBus;
    
    // Route storage (keyed by vehicle ID)
    private array $routes = [];
    private array $routeProgress = [];
    private array $routePositions = [];
    
    // Services
    private RouteAdvancer $advancer;
    private RouteInterpolator $interpolator;
    private RouteDistanceCalculator $distanceCalculator;
    private RouteCompletionDetector $completionDetector;

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
        
        // Get VehicleStore from context
        $vehicleStore = $context->getVehicleStore();
        $this->vehicleRepository = new VehicleRepository($vehicleStore);
        
        // Get EventBus from context
        $this->eventBus = $context->getConfig('event_bus');
        
        // Get SimulationClock from context
        $this->clock = $context->getConfig('simulation_clock') ?? new SimulationClock();
        
        // Load configuration
        $configArray = $context->getConfig('route_config', []);
        $this->configuration = RouteConfiguration::fromArray($configArray);
        
        // Initialize services
        $this->initializeServices();
    }

    public function start(): void
    {
        // Start module
    }

    public function tick(): void
    {
        $this->processRouteAdvancement();
    }

    public function pause(): void
    {
        // Pause module
    }

    public function resume(): void
    {
        // Resume module
    }

    public function stop(): void
    {
        // Stop module
    }

    public function shutdown(): void
    {
        // Cleanup module
    }

    public function getName(): string
    {
        return 'route';
    }

    public function getPriority(): int
    {
        return 80; // Execute after fuel (85)
    }

    public function getDependencies(): array
    {
        return ['vehicle_dynamics', 'fuel'];
    }

    public function getExecutionInterval(): int
    {
        return $this->configuration->getAdvancementIntervalMs();
    }

    /**
     * Initialize services.
     */
    private function initializeServices(): void
    {
        $this->advancer = new RouteAdvancer();
        $this->interpolator = new RouteInterpolator();
        $this->distanceCalculator = new RouteDistanceCalculator();
        $this->completionDetector = new RouteCompletionDetector();
    }

    /**
     * Assign route to vehicle.
     */
    public function assignRoute(string $vehicleId, Route $route): void
    {
        $this->routes[$vehicleId] = $route;
        $this->routeProgress[$vehicleId] = RouteProgress::createInitial($route);
        $this->routePositions[$vehicleId] = RoutePosition::createInitial($route);
        
        // Publish route started event
        $this->publishRouteStarted($vehicleId, $route);
    }

    /**
     * Process route advancement for all vehicles.
     */
    private function processRouteAdvancement(): void
    {
        foreach ($this->routes as $vehicleId => $route) {
            $this->processVehicleRouteAdvancement($vehicleId, $route);
        }
    }

    /**
     * Process route advancement for a single vehicle.
     */
    private function processVehicleRouteAdvancement(string $vehicleId, Route $route): void
    {
        $state = $this->vehicleRepository->getVehicleState($vehicleId);
        $runtime = $this->vehicleRepository->getVehicleRuntime($vehicleId);
        
        if (!$state || !$runtime) {
            return;
        }

        $progress = $this->routeProgress[$vehicleId] ?? null;
        $position = $this->routePositions[$vehicleId] ?? null;
        
        if (!$progress || !$position || $progress->isCompleted) {
            return;
        }

        // Calculate elapsed time
        $elapsedMs = $this->calculateElapsedMs($runtime);

        // Step 1: Advance Progress
        $advancementResult = $this->advancer->advance(
            $route,
            $progress,
            $position,
            $state->currentSpeed,
            $elapsedMs,
        );

        // Step 2: Interpolate Position
        $interpolationResult = $this->interpolator->interpolate($route, $position);
        $position->updateRoutePosition($interpolationResult['position_on_route']);

        // Step 3: Update Route State
        $this->updateRouteState($vehicleId, $progress, $position);

        // Step 4: Detect Events
        $events = $this->completionDetector->detect($route, $progress, $position);
        $this->handleDetectedEvents($vehicleId, $route, $progress, $position, $events);
    }

    /**
     * Update route state.
     */
    private function updateRouteState(string $vehicleId, RouteProgress $progress, RoutePosition $position): void
    {
        $this->routeProgress[$vehicleId] = $progress;
        $this->routePositions[$vehicleId] = $position;
    }

    /**
     * Handle detected events.
     */
    private function handleDetectedEvents(
        string $vehicleId,
        Route $route,
        RouteProgress $progress,
        RoutePosition $position,
        array $events,
    ): void {
        // Handle segment change
        if (isset($events['segment_changed'])) {
            $segmentChange = $events['segment_changed'];
            $progress->setCurrentSegment($segmentChange['new_segment_id']);
            $position->moveToSegment(
                $segmentChange['new_segment_id'],
                0.0,
                $segmentChange['position_on_route']
            );
            $this->publishSegmentChanged($vehicleId, $route, $segmentChange);
        }

        // Handle waypoint reached
        if (isset($events['waypoint_reached'])) {
            $waypointReached = $events['waypoint_reached'];
            $progress->completeWaypoint($waypointReached['waypoint_id']);
            $this->publishWaypointReached($vehicleId, $route, $waypointReached, $progress);
        }

        // Handle route completion
        if (isset($events['route_completed'])) {
            $routeCompleted = $events['route_completed'];
            $progress->markCompleted();
            $this->publishRouteCompleted($vehicleId, $route, $routeCompleted);
        }
    }

    /**
     * Calculate elapsed time since last tick in milliseconds.
     */
    private function calculateElapsedMs($runtime): float
    {
        $currentTime = $this->clock->getCurrentTime();
        $lastTime = $runtime->currentSimulationTime;
        
        $diff = $lastTime->diff($currentTime);
        return ($diff->h * 3600000) + ($diff->i * 60000) + ($diff->s * 1000) + ($diff->f * 1000000);
    }

    /**
     * Publish route started event.
     */
    private function publishRouteStarted(string $vehicleId, Route $route): void
    {
        if ($this->eventBus) {
            $event = new RouteStarted(
                vehicleId: $vehicleId,
                routeId: $route->id,
                origin: $route->origin,
                destination: $route->destination,
                totalDistance: $route->totalDistance,
                timestamp: new \DateTime(),
            );
            $this->eventBus->publish('route.started', $event->toArray());
        }
    }

    /**
     * Publish waypoint reached event.
     */
    private function publishWaypointReached(string $vehicleId, Route $route, array $waypointData, RouteProgress $progress): void
    {
        if ($this->eventBus) {
            $event = new WaypointReached(
                vehicleId: $vehicleId,
                routeId: $route->id,
                waypointId: $waypointData['waypoint_id'],
                waypointName: $waypointData['waypoint_name'],
                waypointType: $waypointData['waypoint_type'],
                distanceTravelled: $progress->distanceTravelled,
                timestamp: new \DateTime(),
            );
            $this->eventBus->publish('route.waypoint_reached', $event->toArray());
        }
    }

    /**
     * Publish segment changed event.
     */
    private function publishSegmentChanged(string $vehicleId, Route $route, array $segmentData): void
    {
        if ($this->eventBus) {
            $event = new RouteSegmentChanged(
                vehicleId: $vehicleId,
                routeId: $route->id,
                previousSegmentId: $segmentData['previous_segment_id'],
                newSegmentId: $segmentData['new_segment_id'],
                positionOnRoute: $segmentData['position_on_route'],
                timestamp: new \DateTime(),
            );
            $this->eventBus->publish('route.segment_changed', $event->toArray());
        }
    }

    /**
     * Publish route completed event.
     */
    private function publishRouteCompleted(string $vehicleId, Route $route, array $completionData): void
    {
        if ($this->eventBus) {
            $event = new RouteCompleted(
                vehicleId: $vehicleId,
                routeId: $route->id,
                origin: $route->origin,
                destination: $route->destination,
                totalDistance: $route->totalDistance,
                actualDuration: $completionData['duration'],
                timestamp: new \DateTime(),
            );
            $this->eventBus->publish('route.completed', $event->toArray());
        }
    }

    /**
     * Get route position output for vehicle.
     */
    public function getRoutePositionOutput(string $vehicleId): ?RoutePositionOutput
    {
        $route = $this->routes[$vehicleId] ?? null;
        $progress = $this->routeProgress[$vehicleId] ?? null;
        $position = $this->routePositions[$vehicleId] ?? null;
        
        if (!$route || !$progress || !$position) {
            return null;
        }
        
        return new RoutePositionOutput(
            routeId: $route->id,
            segmentId: $position->segmentId,
            positionOnSegment: $position->positionOnSegment,
            positionOnRoute: $position->positionOnRoute,
            distanceFromStart: $position->distanceFromStart,
            distanceTravelled: $progress->distanceTravelled,
            remainingDistance: $progress->remainingDistance,
            progressPercentage: $progress->progressPercentage,
            completedWaypoints: $progress->completedWaypoints,
            remainingWaypoints: $progress->remainingWaypoints,
        );
    }

    /**
     * Set route configuration.
     */
    public function setConfiguration(RouteConfiguration $config): void
    {
        $this->configuration = $config;
    }

    /**
     * Get route configuration.
     */
    public function getConfiguration(): RouteConfiguration
    {
        return $this->configuration;
    }
}
