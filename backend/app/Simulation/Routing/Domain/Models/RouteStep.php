<?php

namespace App\Simulation\Routing\Domain\Models;

use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\Polyline;
use App\Simulation\Routing\ValueObjects\SpeedLimit;
use App\Simulation\Routing\Enums\RoadType;
use App\Simulation\Routing\Enums\InstructionType;

/**
 * RouteStep Domain Model.
 * Represents a single step in a route leg with navigation instruction.
 */
readonly class RouteStep
{
    public function __construct(
        public string $id,
        public string $instruction,
        public InstructionType $instructionType,
        public Distance $distance,
        public Duration $duration,
        public RoadType $roadType,
        public Polyline $geometry,
        public ?SpeedLimit $speedLimit,
    ) {}

    /**
     * Create route step.
     */
    public static function create(
        string $id,
        string $instruction,
        InstructionType $instructionType,
        Distance $distance,
        Duration $duration,
        RoadType $roadType,
        Polyline $geometry,
        ?SpeedLimit $speedLimit = null,
    ): self {
        return new self(
            id: $id,
            instruction: $instruction,
            instructionType: $instructionType,
            distance: $distance,
            duration: $duration,
            roadType: $roadType,
            geometry: $geometry,
            speedLimit: $speedLimit,
        );
    }

    /**
     * Check if this is a turn instruction.
     */
    public function isTurn(): bool
    {
        return in_array($this->instructionType, [
            InstructionType::TURN_LEFT,
            InstructionType::TURN_RIGHT,
            InstructionType::TURN_SLIGHT_LEFT,
            InstructionType::TURN_SLIGHT_RIGHT,
        ]);
    }

    /**
     * Check if this is a roundabout instruction.
     */
    public function isRoundabout(): bool
    {
        return in_array($this->instructionType, [
            InstructionType::ROUNDABOUT_ENTER,
            InstructionType::ROUNDABOUT_EXIT,
            InstructionType::ROUNDABOUT_STRAIGHT,
        ]);
    }

    /**
     * Check if this is a ferry instruction.
     */
    public function isFerry(): bool
    {
        return in_array($this->instructionType, [
            InstructionType::FERRY_ENTER,
            InstructionType::FERRY_EXIT,
        ]) || $this->roadType === RoadType::FERRY;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'instruction' => $this->instruction,
            'instruction_type' => $this->instructionType->value,
            'distance' => $this->distance->toArray(),
            'duration' => $this->duration->toArray(),
            'road_type' => $this->roadType->value,
            'geometry' => $this->geometry->toArray(),
            'speed_limit' => $this->speedLimit?->toArray(),
        ];
    }
}
