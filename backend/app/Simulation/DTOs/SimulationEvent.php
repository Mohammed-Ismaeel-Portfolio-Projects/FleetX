<?php

namespace App\Simulation\DTOs;

/**
 * Base DTO for simulation events.
 * Used for event bus communication between modules.
 */
readonly class SimulationEvent
{
    public function __construct(
        public string $type,
        public array $payload = [],
        public ?string $sourceModule = null,
        public ?\DateTime $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    /**
     * Create event with source module.
     */
    public static function fromSource(string $type, string $sourceModule, array $payload = []): self
    {
        return new self($type, $payload, $sourceModule);
    }
}
