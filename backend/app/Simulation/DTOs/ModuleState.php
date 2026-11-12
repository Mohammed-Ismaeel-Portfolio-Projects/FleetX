<?php

namespace App\Simulation\DTOs;

/**
 * DTO for module state snapshot.
 * Used for debugging and state inspection.
 */
readonly class ModuleState
{
    public function __construct(
        public string $moduleName,
        public string $status,
        public array $data = [],
        public ?\DateTime $lastUpdated = null,
    ) {
        $this->lastUpdated = $lastUpdated ?? new \DateTime();
    }
}
