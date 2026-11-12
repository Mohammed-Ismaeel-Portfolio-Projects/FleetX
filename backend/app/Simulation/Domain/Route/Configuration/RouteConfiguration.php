<?php

namespace App\Simulation\Domain\Route\Configuration;

/**
 * RouteConfiguration.
 * Configuration for route simulation.
 */
class RouteConfiguration
{
    public function __construct(
        private int $advancementIntervalMs = 500, // Default 500ms
        private bool $enableWaypointDetection = true,
        private bool $enableSegmentDetection = true,
        private float $waypointProximityThreshold = 0.01, // 1% of route
        private array $roadTypeSpeedAdjustments = [],
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            advancementIntervalMs: $config['advancement_interval_ms'] ?? 500,
            enableWaypointDetection: $config['enable_waypoint_detection'] ?? true,
            enableSegmentDetection: $config['enable_segment_detection'] ?? true,
            waypointProximityThreshold: $config['waypoint_proximity_threshold'] ?? 0.01,
            roadTypeSpeedAdjustments: $config['road_type_speed_adjustments'] ?? [],
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'advancement_interval_ms' => $this->advancementIntervalMs,
            'enable_waypoint_detection' => $this->enableWaypointDetection,
            'enable_segment_detection' => $this->enableSegmentDetection,
            'waypoint_proximity_threshold' => $this->waypointProximityThreshold,
            'road_type_speed_adjustments' => $this->roadTypeSpeedAdjustments,
        ];
    }

    /**
     * Get advancement interval in milliseconds.
     */
    public function getAdvancementIntervalMs(): int
    {
        return $this->advancementIntervalMs;
    }

    /**
     * Set advancement interval in milliseconds.
     */
    public function setAdvancementIntervalMs(int $intervalMs): void
    {
        $this->advancementIntervalMs = $intervalMs;
    }

    /**
     * Check if waypoint detection is enabled.
     */
    public function isWaypointDetectionEnabled(): bool
    {
        return $this->enableWaypointDetection;
    }

    /**
     * Enable or disable waypoint detection.
     */
    public function setWaypointDetectionEnabled(bool $enabled): void
    {
        $this->enableWaypointDetection = $enabled;
    }

    /**
     * Check if segment detection is enabled.
     */
    public function isSegmentDetectionEnabled(): bool
    {
        return $this->enableSegmentDetection;
    }

    /**
     * Enable or disable segment detection.
     */
    public function setSegmentDetectionEnabled(bool $enabled): void
    {
        $this->enableSegmentDetection = $enabled;
    }

    /**
     * Get waypoint proximity threshold.
     */
    public function getWaypointProximityThreshold(): float
    {
        return $this->waypointProximityThreshold;
    }

    /**
     * Set waypoint proximity threshold.
     */
    public function setWaypointProximityThreshold(float $threshold): void
    {
        $this->waypointProximityThreshold = $threshold;
    }

    /**
     * Get road type speed adjustments.
     */
    public function getRoadTypeSpeedAdjustments(): array
    {
        return $this->roadTypeSpeedAdjustments;
    }

    /**
     * Set road type speed adjustments.
     */
    public function setRoadTypeSpeedAdjustments(array $adjustments): void
    {
        $this->roadTypeSpeedAdjustments = $adjustments;
    }
}
