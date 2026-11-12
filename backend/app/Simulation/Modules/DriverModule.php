<?php

namespace App\Simulation\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;

/**
 * Driver Module.
 * Manages driver state simulation.
 */
class DriverModule implements SimulationModuleInterface
{
    private EngineContextInterface $context;

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
    }

    public function start(): void
    {
        // Start module
    }

    public function tick(): void
    {
        // Execute tick logic
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
        return 'driver';
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getExecutionInterval(): int
    {
        return 500; // 500 ms
    }
}
