<?php

namespace App\Simulation\Core;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\EventBusInterface;
use App\Simulation\Contracts\SimulationModuleInterface;

/**
 * Main Simulation Engine.
 * Orchestrates all core components and manages simulation lifecycle.
 */
class SimulationEngine
{
    private EngineContextInterface $context;
    private EventBusInterface $eventBus;
    private SimulationClock $clock;
    private TickManager $tickManager;
    private ModuleLoader $moduleLoader;
    private Scheduler $scheduler;
    private bool $initialized = false;

    public function __construct()
    {
        $this->context = new EngineContext();
        $this->eventBus = new EventBus();
        $this->clock = new SimulationClock();
        $this->tickManager = new TickManager($this->clock);
        $this->moduleLoader = new ModuleLoader();
        $this->scheduler = new Scheduler();
    }

    /**
     * Initialize the engine with configuration.
     */
    public function initialize(array $config = []): void
    {
        foreach ($config as $key => $value) {
            $this->context->setConfig($key, $value);
        }

        $tickInterval = $this->context->getConfig('tick_interval_ms', 100);
        $this->tickManager->setTickIntervalMs($tickInterval);

        $timeScale = $this->context->getConfig('time_scale', 1.0);
        $this->clock->setTimeScale($timeScale);

        $this->initialized = true;
    }

    /**
     * Start the simulation.
     */
    public function start(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Engine not initialized');
        }

        $this->context->setRunning(true);
        $this->tickManager->setRunning(true);

        foreach ($this->moduleLoader->getLoadedModules() as $module) {
            $module->start();
        }
    }

    /**
     * Pause the simulation.
     */
    public function pause(): void
    {
        $this->context->setRunning(false);
        $this->tickManager->setRunning(false);

        foreach ($this->moduleLoader->getLoadedModules() as $module) {
            $module->pause();
        }
    }

    /**
     * Resume the simulation.
     */
    public function resume(): void
    {
        $this->context->setRunning(true);
        $this->tickManager->setRunning(true);

        foreach ($this->moduleLoader->getLoadedModules() as $module) {
            $module->resume();
        }
    }

    /**
     * Stop the simulation.
     */
    public function stop(): void
    {
        $this->context->setRunning(false);
        $this->tickManager->setRunning(false);

        foreach ($this->moduleLoader->getLoadedModules() as $module) {
            $module->stop();
        }
    }

    /**
     * Shutdown the engine.
     */
    public function shutdown(): void
    {
        $this->stop();
        $this->moduleLoader->unloadAll();
        $this->eventBus->clearAll();
        $this->scheduler->clearAll();
        $this->initialized = false;
    }

    /**
     * Execute a single simulation tick.
     * SimulationClock advances by the configured tick interval (× time scale).
     */
    public function tick(): void
    {
        if (!$this->context->isRunning()) {
            return;
        }

        $intervalMs = $this->tickManager->getTickIntervalMs();
        $scaledMs = (int) max(1, (int) round($intervalMs * $this->clock->getTimeScale()));
        $this->clock->advanceMilliseconds($scaledMs);

        $this->context->incrementTickCount();
        $this->tickManager->executeTick();
        $this->scheduler->executeDueTasks($this->clock->getCurrentTime());
        $this->context->setSimulationTime($this->clock->getCurrentTime());
    }

    /**
     * Register a module.
     */
    public function registerModule(SimulationModuleInterface $module): void
    {
        $module->initialize($this->context);
        $this->tickManager->registerModule($module);
        $this->moduleLoader->attachModule($module);
    }

    /**
     * Load a module by name.
     */
    public function loadModule(string $name): ?SimulationModuleInterface
    {
        $module = $this->moduleLoader->loadModule($name);
        if ($module) {
            $module->initialize($this->context);
            $this->tickManager->registerModule($module);
        }
        return $module;
    }

    /**
     * Get engine context.
     */
    public function getContext(): EngineContextInterface
    {
        return $this->context;
    }

    /**
     * Get event bus.
     */
    public function getEventBus(): EventBusInterface
    {
        return $this->eventBus;
    }

    /**
     * Get simulation clock.
     */
    public function getClock(): SimulationClock
    {
        return $this->clock;
    }

    /**
     * Get tick manager.
     */
    public function getTickManager(): TickManager
    {
        return $this->tickManager;
    }

    /**
     * Get module loader.
     */
    public function getModuleLoader(): ModuleLoader
    {
        return $this->moduleLoader;
    }

    /**
     * Get scheduler.
     */
    public function getScheduler(): Scheduler
    {
        return $this->scheduler;
    }

    /**
     * Check if engine is initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Check if engine is running.
     */
    public function isRunning(): bool
    {
        return $this->context->isRunning();
    }
}
