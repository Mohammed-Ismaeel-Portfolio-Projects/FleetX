<?php

namespace App\Simulation\Domain\GPS\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Contracts\EventBusInterface;
use App\Simulation\Domain\GPS\Services\PositionConverter;
use App\Simulation\Domain\GPS\Services\GPSNoiseModel;
use App\Simulation\Domain\GPS\Services\GPSQualityEstimator;
use App\Simulation\Domain\GPS\Services\HeadingCalculator;
use App\Simulation\Domain\GPS\Services\SpeedProjector;
use App\Simulation\Domain\GPS\Configuration\GPSConfiguration;
use App\Simulation\Domain\GPS\Events\GPSUpdated;
use App\Simulation\Domain\GPS\DTOs\GPSOutput;
use App\Simulation\Domain\GPS\Models\Coordinate;
use App\Simulation\Domain\GPS\Models\GPSPosition;
use App\Simulation\Domain\GPS\Models\GPSSignal;
use App\Simulation\Domain\Route\Modules\RouteModule;
use App\Simulation\Domain\Vehicle\Repositories\VehicleRepository;
use App\Simulation\Core\SimulationClock;

/**
 * GPSModule - GPS Simulation Orchestrator.
 * Converts logical route position to geographic coordinates.
 */
class GPSModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;
    private VehicleRepository $vehicleRepository;
    private SimulationClock $clock;
    private GPSConfiguration $configuration;
    private EventBusInterface $eventBus;
    private ?RouteModule $routeModule;
    
    // Previous positions for heading calculation (keyed by vehicle ID)
    private array $previousCoordinates = [];
    private array $previousSpeeds = [];
    
    // Services
    private PositionConverter $positionConverter;
    private GPSNoiseModel $noiseModel;
    private GPSQualityEstimator $qualityEstimator;
    private HeadingCalculator $headingCalculator;
    private SpeedProjector $speedProjector;

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
        
        // Get RouteModule from context
        $this->routeModule = $context->getConfig('route_module');
        
        // Load configuration
        $configArray = $context->getConfig('gps_config', []);
        $this->configuration = GPSConfiguration::fromArray($configArray);
        
        // Initialize services
        $this->initializeServices();
    }

    public function start(): void
    {
        // Start module
    }

    public function tick(): void
    {
        $this->processGPSUpdates();
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
        return 'gps';
    }

    public function getPriority(): int
    {
        return 75; // Execute after route (80)
    }

    public function getDependencies(): array
    {
        return ['route'];
    }

    public function getExecutionInterval(): int
    {
        return $this->configuration->getUpdateIntervalMs();
    }

    /**
     * Initialize services.
     */
    private function initializeServices(): void
    {
        $this->positionConverter = new PositionConverter();
        $this->noiseModel = new GPSNoiseModel();
        $this->qualityEstimator = new GPSQualityEstimator();
        $this->headingCalculator = new HeadingCalculator();
        $this->speedProjector = new SpeedProjector();
        
        // Configure noise model based on configuration
        if ($this->configuration->isNoiseModelEnabled()) {
            $this->noiseModel->enable();
            $this->noiseModel->setDriftMagnitude($this->configuration->getNoiseDriftMagnitude());
            $this->noiseModel->setOffsetMagnitude($this->configuration->getNoiseOffsetMagnitude());
            $this->noiseModel->setJitterMagnitude($this->configuration->getNoiseJitterMagnitude());
        }
    }

    /**
     * Process GPS updates for all vehicles.
     */
    private function processGPSUpdates(): void
    {
        if (!$this->routeModule) {
            return;
        }
        
        $vehicles = $this->vehicleRepository->getAll();
        
        foreach ($vehicles as $vehicle) {
            $this->processVehicleGPSUpdate($vehicle);
        }
    }

    /**
     * Process GPS update for a single vehicle.
     */
    private function processVehicleGPSUpdate($vehicle): void
    {
        $state = $this->vehicleRepository->getVehicleState($vehicle->id);
        
        if (!$state) {
            return;
        }

        // Get route position from RouteModule
        $routePositionOutput = $this->routeModule->getRoutePositionOutput($vehicle->id);
        
        if (!$routePositionOutput) {
            return;
        }

        // Get route data for conversion
        // Note: In production, we would need access to the Route object
        // For now, we'll use a simplified approach
        
        // Step 1: Convert Position
        $coordinate = $this->convertRoutePosition($vehicle->id, $routePositionOutput);
        
        // Step 2: Apply Noise (optional)
        if ($this->configuration->isNoiseModelEnabled()) {
            $coordinate = $this->noiseModel->applyNoise($coordinate);
        }
        
        // Step 3: Calculate Heading
        $heading = $this->calculateHeading($vehicle->id, $coordinate);
        
        // Step 4: Estimate Signal Quality
        $signal = $this->estimateSignalQuality();
        
        // Step 5: Project Speed
        $speed = $this->projectSpeed($vehicle->id, $state->currentSpeed);
        
        // Step 6: Generate GPSOutput
        $gpsPosition = new GPSPosition(
            coordinate: $coordinate,
            heading: $heading,
            speed: $speed,
            timestamp: $this->clock->getCurrentTime(),
        );
        
        $gpsOutput = new GPSOutput(
            vehicleId: $vehicle->id,
            position: $gpsPosition,
            signal: $signal,
        );
        
        // Step 7: Publish GPSUpdated event
        $this->publishGPSUpdated($gpsOutput);
        
        // Store previous coordinate for next heading calculation
        $this->previousCoordinates[$vehicle->id] = $coordinate;
        $this->previousSpeeds[$vehicle->id] = $speed;
    }

    /**
     * Convert route position to coordinate.
     */
    private function convertRoutePosition(string $vehicleId, $routePositionOutput): Coordinate
    {
        // TODO: In production, get the Route object from RouteModule
        // For now, create a simple coordinate based on route position
        
        // Placeholder implementation
        $baseLatitude = 40.7128;
        $baseLongitude = -74.0060;
        
        $latitudeOffset = $routePositionOutput->positionOnRoute * 0.1;
        $longitudeOffset = $routePositionOutput->positionOnRoute * 0.1;
        
        return new Coordinate(
            latitude: $baseLatitude + $latitudeOffset,
            longitude: $baseLongitude + $longitudeOffset,
            altitude: 0.0,
        );
    }

    /**
     * Calculate heading.
     */
    private function calculateHeading(string $vehicleId, Coordinate $currentCoordinate): float
    {
        $previousCoordinate = $this->previousCoordinates[$vehicleId] ?? null;
        
        if (!$previousCoordinate) {
            // Default heading if no previous position
            return 0.0; // North
        }
        
        return $this->headingCalculator->calculate($previousCoordinate, $currentCoordinate);
    }

    /**
     * Estimate signal quality.
     */
    private function estimateSignalQuality(): GPSSignal
    {
        return $this->qualityEstimator->estimateWithFactors(
            baseAccuracy: $this->configuration->getBaseAccuracy(),
            environment: $this->configuration->getEnvironment(),
        );
    }

    /**
     * Project speed.
     */
    private function projectSpeed(string $vehicleId, float $vehicleSpeed): float
    {
        if ($this->configuration->isSpeedSmoothingEnabled()) {
            $previousSpeed = $this->previousSpeeds[$vehicleId] ?? $vehicleSpeed;
            return $this->speedProjector->projectWithSmoothing(
                $vehicleSpeed,
                $previousSpeed,
                $this->configuration->getSpeedSmoothingFactor(),
            );
        }
        
        return $this->speedProjector->project($vehicleSpeed);
    }

    /**
     * Publish GPS updated event.
     */
    private function publishGPSUpdated(GPSOutput $gpsOutput): void
    {
        if ($this->eventBus) {
            $event = new GPSUpdated(
                vehicleId: $gpsOutput->vehicleId,
                latitude: $gpsOutput->position->coordinate->latitude,
                longitude: $gpsOutput->position->coordinate->longitude,
                altitude: $gpsOutput->position->coordinate->altitude,
                heading: $gpsOutput->position->heading,
                speed: $gpsOutput->position->speed,
                accuracy: $gpsOutput->signal->accuracy,
                satelliteCount: $gpsOutput->signal->satelliteCount,
                timestamp: $gpsOutput->position->timestamp,
            );
            $this->eventBus->publish('gps.updated', $event->toArray());
        }
    }

    /**
     * Set GPS configuration.
     */
    public function setConfiguration(GPSConfiguration $config): void
    {
        $this->configuration = $config;
        
        // Reconfigure noise model
        if ($this->configuration->isNoiseModelEnabled()) {
            $this->noiseModel->enable();
            $this->noiseModel->setDriftMagnitude($this->configuration->getNoiseDriftMagnitude());
            $this->noiseModel->setOffsetMagnitude($this->configuration->getNoiseOffsetMagnitude());
            $this->noiseModel->setJitterMagnitude($this->configuration->getNoiseJitterMagnitude());
        } else {
            $this->noiseModel->disable();
        }
    }

    /**
     * Get GPS configuration.
     */
    public function getConfiguration(): GPSConfiguration
    {
        return $this->configuration;
    }

    /**
     * Get GPS output for vehicle.
     */
    public function getGPSOutput(string $vehicleId): ?GPSOutput
    {
        // This would return the last generated GPS output
        // For now, return null as we don't store outputs
        return null;
    }
}
