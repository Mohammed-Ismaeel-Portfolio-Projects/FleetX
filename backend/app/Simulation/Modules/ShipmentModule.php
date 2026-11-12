<?php

namespace App\Simulation\Modules;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;

/**
 * Shipment Module.
 * Manages shipment state simulation.
 */
class ShipmentModule implements SimulationModuleInterface
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
        return 'shipment';
    }

    public function getPriority(): int
    {
        return 90;
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getExecutionInterval(): int
    {
        return 2000; // 2000 ms
    }
}
