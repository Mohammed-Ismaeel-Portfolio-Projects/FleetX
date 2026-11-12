<?php

namespace App\Simulation\Core;

use App\Simulation\Contracts\SimulationModuleInterface;

/**
 * Tick Manager manages the execution of modules per simulation tick.
 * Supports interval-based execution for scalability.
 * Uses SimulationClock for all timing decisions.
 */
class TickManager
{
    private array $modules = [];
    private array $lastExecutionTimes = [];
    private int $tickIntervalMs = 100; // Default 100ms per tick
    private bool $running = false;
    private SimulationClock $clock;

    /**
     * Constructor with SimulationClock injection.
     */
    public function __construct(SimulationClock $clock)
    {
        $this->clock = $clock;
    }

    /**
     * Register a module for tick execution.
     */
    public function registerModule(SimulationModuleInterface $module): void
    {
        $moduleName = $module->getName();
        $this->modules[$moduleName] = $module;
        $this->lastExecutionTimes[$moduleName] = 0; // Initialize to never executed
        $this->sortModules();
    }

    /**
     * Unregister a module.
     */
    public function unregisterModule(string $moduleName): void
    {
        unset($this->modules[$moduleName]);
        unset($this->lastExecutionTimes[$moduleName]);
    }

    /**
     * Sort modules by priority.
     */
    private function sortModules(): void
    {
        uasort($this->modules, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
    }

    /**
     * Execute a single tick for all registered modules.
     * Only executes modules whose execution interval has elapsed.
     *
     * @param int $elapsedMs Elapsed time since last tick in simulation time
     */
    public function executeTick(int $elapsedMs = 0): void
    {
        $simulationTime = $this->clock->getCurrentTime();
        $simulationTimeMs = $simulationTime->getTimestamp() * 1000 + $simulationTime->format('u') / 1000;

        foreach ($this->modules as $module) {
            $moduleName = $module->getName();
            $executionInterval = $module->getExecutionInterval();
            $lastExecution = $this->lastExecutionTimes[$moduleName] ?? 0;

            // Check if interval has elapsed in simulation time
            if ($simulationTimeMs - $lastExecution >= $executionInterval) {
                $module->tick();
                $this->lastExecutionTimes[$moduleName] = $simulationTimeMs;
            }
        }
    }

    /**
     * Force execute a specific module regardless of interval.
     */
    public function executeModule(string $moduleName): void
    {
        if (isset($this->modules[$moduleName])) {
            $this->modules[$moduleName]->tick();
            $simulationTime = $this->clock->getCurrentTime();
            $simulationTimeMs = $simulationTime->getTimestamp() * 1000 + $simulationTime->format('u') / 1000;
            $this->lastExecutionTimes[$moduleName] = $simulationTimeMs;
        }
    }

    /**
     * Get tick interval in milliseconds.
     */
    public function getTickIntervalMs(): int
    {
        return $this->tickIntervalMs;
    }

    /**
     * Set tick interval in milliseconds.
     */
    public function setTickIntervalMs(int $intervalMs): void
    {
        $this->tickIntervalMs = max(1, $intervalMs);
    }

    /**
     * Get registered modules.
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Get last execution time for a module.
     */
    public function getLastExecutionTime(string $moduleName): ?int
    {
        return $this->lastExecutionTimes[$moduleName] ?? null;
    }

    /**
     * Get all last execution times.
     */
    public function getAllLastExecutionTimes(): array
    {
        return $this->lastExecutionTimes;
    }

    /**
     * Reset execution times for all modules.
     */
    public function resetExecutionTimes(): void
    {
        foreach (array_keys($this->lastExecutionTimes) as $moduleName) {
            $this->lastExecutionTimes[$moduleName] = 0;
        }
    }

    /**
     * Get SimulationClock.
     */
    public function getClock(): SimulationClock
    {
        return $this->clock;
    }

    /**
     * Check if tick manager is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Set running state.
     */
    public function setRunning(bool $running): void
    {
        $this->running = $running;
    }
}
