<?php

namespace App\Simulation\Domain\Environment\DTOs;

use App\Simulation\Domain\Environment\Models\EnvironmentState;

/**
 * EnvironmentOutput DTO.
 * Output from environment processing.
 */
readonly class EnvironmentOutput
{
    public function __construct(
        public EnvironmentState $state,
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return $this->state->toArray();
    }
}
