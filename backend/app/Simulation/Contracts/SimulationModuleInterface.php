<?php

namespace App\Simulation\Contracts;

/**
 * Contract for all simulation modules.
 * Every module must implement this lifecycle.
 */
interface SimulationModuleInterface
{
    /**
     * Initialize the module with engine context.
     */
    public function initialize(EngineContextInterface $context): void;

    /**
     * Start the module.
     */
    public function start(): void;

    /**
     * Execute module logic for a single simulation tick.
     */
    public function tick(): void;

    /**
     * Pause the module.
     */
    public function pause(): void;

    /**
     * Resume the module from paused state.
     */
    public function resume(): void;

    /**
     * Stop the module.
     */
    public function stop(): void;

    /**
     * Shutdown and cleanup the module.
     */
    public function shutdown(): void;

    /**
     * Get module name.
     */
    public function getName(): string;

    /**
     * Get module priority for execution order.
     * Lower numbers execute first.
     */
    public function getPriority(): int;

    /**
     * Get module dependencies.
     * Returns array of module names this module depends on.
     */
    public function getDependencies(): array;

    /**
     * Get execution interval in milliseconds.
     * Module will only execute when this interval has elapsed.
     */
    public function getExecutionInterval(): int;
}
