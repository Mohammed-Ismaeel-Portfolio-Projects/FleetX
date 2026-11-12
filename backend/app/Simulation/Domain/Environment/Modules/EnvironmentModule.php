<?php

namespace App\Simulation\Domain\Environment\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Contracts\EventBusInterface;
use App\Simulation\Domain\Environment\Services\WeatherGenerator;
use App\Simulation\Domain\Environment\Services\RoadConditionCalculator;
use App\Simulation\Domain\Environment\Services\TrafficDensityCalculator;
use App\Simulation\Domain\Environment\Services\VisibilityCalculator;
use App\Simulation\Domain\Environment\Services\SurfaceConditionCalculator;
use App\Simulation\Domain\Environment\Services\EnvironmentMerger;
use App\Simulation\Domain\Environment\Configuration\EnvironmentConfiguration;
use App\Simulation\Domain\Environment\Events\EnvironmentChanged;
use App\Simulation\Domain\Environment\DTOs\EnvironmentOutput;
use App\Simulation\Domain\Environment\Models\EnvironmentState;
use App\Simulation\Core\SimulationClock;

/**
 * EnvironmentModule - Environment Simulation Orchestrator.
 * Manages shared immutable environment context for all simulation modules.
 */
class EnvironmentModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;
    private SimulationClock $clock;
    private EnvironmentConfiguration $configuration;
    private EventBusInterface $eventBus;
    
    // Current environment state (shared, immutable)
    private ?EnvironmentState $currentState = null;
    
    // Services
    private WeatherGenerator $weatherGenerator;
    private RoadConditionCalculator $roadConditionCalculator;
    private TrafficDensityCalculator $trafficDensityCalculator;
    private VisibilityCalculator $visibilityCalculator;
    private SurfaceConditionCalculator $surfaceConditionCalculator;
    private EnvironmentMerger $environmentMerger;

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
        
        // Get SimulationClock from context
        $this->clock = $context->getConfig('simulation_clock') ?? new SimulationClock();
        
        // Get EventBus from context
        $this->eventBus = $context->getConfig('event_bus');
        
        // Load configuration
        $configArray = $context->getConfig('environment_config', []);
        $this->configuration = EnvironmentConfiguration::fromArray($configArray);
        
        // Initialize services
        $this->initializeServices();
        
        // Generate initial environment state
        $this->generateEnvironmentState();
    }

    public function start(): void
    {
        // Start module
    }

    public function tick(): void
    {
        $this->processEnvironmentUpdate();
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
        return 'environment';
    }

    public function getPriority(): int
    {
        return 100; // Execute immediately after SimulationClock
    }

    public function getDependencies(): array
    {
        return []; // No dependencies - executes first
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
        $this->weatherGenerator = new WeatherGenerator();
        $this->roadConditionCalculator = new RoadConditionCalculator();
        $this->trafficDensityCalculator = new TrafficDensityCalculator();
        $this->visibilityCalculator = new VisibilityCalculator();
        $this->surfaceConditionCalculator = new SurfaceConditionCalculator();
        $this->environmentMerger = new EnvironmentMerger();
        
        // Configure services based on configuration
        $this->weatherGenerator->setScenario($this->configuration->getWeatherScenario());
        $this->trafficDensityCalculator->setScenario($this->configuration->getTrafficScenario());
        $this->surfaceConditionCalculator->setDefaultSurfaceType($this->configuration->getDefaultSurfaceType());
    }

    /**
     * Process environment update.
     */
    private function processEnvironmentUpdate(): void
    {
        // Generate new environment state
        $previousState = $this->currentState;
        $this->generateEnvironmentState();
        
        // Check if state changed
        if ($this->hasStateChanged($previousState, $this->currentState)) {
            $this->publishEnvironmentChanged();
        }
    }

    /**
     * Generate environment state.
     */
    private function generateEnvironmentState(): void
    {
        // Step 1: Generate Weather
        $weather = $this->weatherGenerator->generate($this->clock);
        
        // Step 2: Calculate Road Condition
        $roadCondition = $this->roadConditionCalculator->calculate($weather);
        
        // Step 3: Calculate Traffic
        $trafficCondition = $this->trafficDensityCalculator->calculate($this->clock);
        
        // Step 4: Calculate Visibility
        $visibility = $this->visibilityCalculator->calculate($weather);
        
        // Step 5: Calculate Surface
        $surfaceCondition = $this->surfaceConditionCalculator->calculate($weather);
        
        // Step 6: Merge into EnvironmentState
        $this->currentState = $this->environmentMerger->merge(
            weather: $weather,
            temperature: $this->configuration->getBaseTemperature(),
            humidity: $this->configuration->getBaseHumidity(),
            windSpeed: $this->configuration->getBaseWindSpeed(),
            visibility: $visibility,
            roadCondition: $roadCondition,
            trafficCondition: $trafficCondition,
            surfaceCondition: $surfaceCondition,
        );
    }

    /**
     * Check if environment state changed.
     */
    private function hasStateChanged(?EnvironmentState $previous, EnvironmentState $current): bool
    {
        if (!$previous) {
            return true;
        }
        
        // Check if any significant field changed
        return $previous->weather->weatherType !== $current->weather->weatherType
            || $previous->roadCondition->condition !== $current->roadCondition->condition
            || $previous->trafficCondition->density !== $current->trafficCondition->density
            || abs($previous->visibility->visibilityDistance - $current->visibility->visibilityDistance) > 0.5;
    }

    /**
     * Publish environment changed event.
     */
    private function publishEnvironmentChanged(): void
    {
        if (!$this->currentState || !$this->eventBus) {
            return;
        }
        
        $event = new EnvironmentChanged(
            weatherType: $this->currentState->weather->weatherType,
            temperature: $this->currentState->temperature,
            humidity: $this->currentState->humidity,
            windSpeed: $this->currentState->windSpeed,
            visibilityDistance: $this->currentState->visibility->visibilityDistance,
            roadCondition: $this->currentState->roadCondition->condition,
            roadFriction: $this->currentState->roadFriction,
            trafficDensity: $this->currentState->trafficCondition->density,
            trafficSpeedModifier: $this->currentState->trafficCondition->averageSpeedModifier,
            surfaceType: $this->currentState->surfaceCondition->surfaceType,
            surfaceFriction: $this->currentState->surfaceCondition->frictionCoefficient,
            timestamp: $this->currentState->timestamp,
        );
        
        $this->eventBus->publish('environment.changed', $event->toArray());
    }

    /**
     * Get current environment state.
     */
    public function getCurrentState(): ?EnvironmentState
    {
        return $this->currentState;
    }

    /**
     * Get environment output.
     */
    public function getOutput(): ?EnvironmentOutput
    {
        if (!$this->currentState) {
            return null;
        }
        
        return new EnvironmentOutput($this->currentState);
    }

    /**
     * Set environment configuration.
     */
    public function setConfiguration(EnvironmentConfiguration $config): void
    {
        $this->configuration = $config;
        
        // Reconfigure services
        $this->weatherGenerator->setScenario($this->configuration->getWeatherScenario());
        $this->trafficDensityCalculator->setScenario($this->configuration->getTrafficScenario());
        $this->surfaceConditionCalculator->setDefaultSurfaceType($this->configuration->getDefaultSurfaceType());
    }

    /**
     * Get environment configuration.
     */
    public function getConfiguration(): EnvironmentConfiguration
    {
        return $this->configuration;
    }
}
