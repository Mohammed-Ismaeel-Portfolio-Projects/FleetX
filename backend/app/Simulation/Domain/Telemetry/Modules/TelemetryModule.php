<?php

namespace App\Simulation\Domain\Telemetry\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Contracts\EventBusInterface;
use App\Simulation\Domain\Telemetry\Collectors\VehicleTelemetryCollector;
use App\Simulation\Domain\Telemetry\Validators\TelemetryValidator;
use App\Simulation\Domain\Telemetry\Encoders\TelemetryEncoder;
use App\Simulation\Domain\Telemetry\Encoders\JsonTelemetryEncoder;
use App\Simulation\Domain\Telemetry\Publishers\TelemetryPublisher;
use App\Simulation\Domain\Telemetry\Publishers\EventBusTelemetryPublisher;
use App\Simulation\Domain\Telemetry\Configuration\TelemetryConfiguration;
use App\Simulation\Domain\Vehicle\Repositories\VehicleRepository;
use App\Simulation\Core\SimulationClock;

/**
 * TelemetryModule - Telemetry Processing Pipeline Orchestrator.
 * Executes the telemetry pipeline: Collect → Validate → Encode → Publish.
 */
class TelemetryModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;
    private VehicleRepository $vehicleRepository;
    private SimulationClock $clock;
    private TelemetryConfiguration $configuration;
    private EventBusInterface $eventBus;
    
    // Pipeline components
    private VehicleTelemetryCollector $collector;
    private TelemetryValidator $validator;
    private TelemetrySignalMapper $signalMapper;
    private TelemetryEncoder $encoder;
    private TelemetryPublisher $publisher;

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
        $configArray = $context->getConfig('telemetry_config', []);
        $this->configuration = TelemetryConfiguration::fromArray($configArray);
        
        // Initialize pipeline components
        $this->initializePipeline();
    }

    public function start(): void
    {
        // Start module
    }

    public function tick(): void
    {
        $this->processTelemetryPipeline();
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
        return 'telemetry';
    }

    public function getPriority(): int
    {
        return 90; // Execute after vehicle_dynamics (95)
    }

    public function getDependencies(): array
    {
        return ['vehicle_dynamics'];
    }

    public function getExecutionInterval(): int
    {
        return $this->configuration->getCollectionIntervalMs();
    }

    /**
     * Initialize pipeline components.
     */
    private function initializePipeline(): void
    {
        $this->collector = new VehicleTelemetryCollector($this->clock);
        $this->validator = new TelemetryValidator();
        $this->signalMapper = new TelemetrySignalMapper();
        $this->encoder = new JsonTelemetryEncoder();
        $this->publisher = new EventBusTelemetryPublisher($this->eventBus);
    }

    /**
     * Process telemetry pipeline for all vehicles.
     */
    private function processTelemetryPipeline(): void
    {
        $vehicles = $this->vehicleRepository->getAll();
        
        foreach ($vehicles as $vehicle) {
            $this->processVehicleTelemetry($vehicle);
        }
    }

    /**
     * Process telemetry pipeline for a single vehicle.
     */
    private function processVehicleTelemetry($vehicle): void
    {
        $state = $this->vehicleRepository->getVehicleState($vehicle->id);
        $runtime = $this->vehicleRepository->getVehicleRuntime($vehicle->id);
        
        if (!$state || !$runtime) {
            return;
        }

        // Step 1: Collect
        $snapshot = $this->collector->collect($vehicle, $state, $runtime);
        
        // Step 2: Validate
        if ($this->configuration->isValidationEnabled()) {
            $validationResult = $this->validator->validate($snapshot);
            if (!$validationResult->isValid) {
                // Skip publishing if validation fails
                return;
            }
        }
        
        // Step 3: Signal Mapping
        $signalCollection = $this->signalMapper->map($snapshot);
        
        // Step 4: Encode
        $packet = $this->encoder->encode($signalCollection);
        
        // Step 5: Publish
        $this->publisher->publish($packet);
    }

    /**
     * Set telemetry configuration.
     */
    public function setConfiguration(TelemetryConfiguration $config): void
    {
        $this->configuration = $config;
    }

    /**
     * Get telemetry configuration.
     */
    public function getConfiguration(): TelemetryConfiguration
    {
        return $this->configuration;
    }

    /**
     * Set custom encoder.
     */
    public function setEncoder(TelemetryEncoder $encoder): void
    {
        $this->encoder = $encoder;
    }

    /**
     * Set custom publisher.
     */
    public function setPublisher(TelemetryPublisher $publisher): void
    {
        $this->publisher = $publisher;
    }
}
