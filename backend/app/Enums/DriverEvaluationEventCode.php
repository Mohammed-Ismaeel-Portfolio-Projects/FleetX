<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Supported / planned evaluation event taxonomy (DRIVER-EVAL-02).
 *
 * Only codes marked CURRENTLY_SUPPORTED are eligible for persistence in early phases.
 * Harsh cornering and similar unavailable signals are FUTURE_ONLY.
 */
enum DriverEvaluationEventCode: string
{
    // Safety
    case SPEEDING = 'speeding';
    case HARSH_BRAKING = 'harsh_braking';
    case HARSH_ACCELERATION = 'harsh_acceleration';
    case SUDDEN_STOP = 'sudden_stop';
    case HARSH_CORNERING = 'harsh_cornering';

    // Operational
    case EXCESSIVE_IDLE = 'excessive_idle';
    case PROLONGED_STOP = 'prolonged_stop';
    case WORKFLOW_VIOLATION = 'workflow_violation';

    // Route / compliance
    case ROUTE_DEVIATION = 'route_deviation';

    // Timing
    case LATE_DEPARTURE = 'late_departure';
    case LATE_ARRIVAL = 'late_arrival';
    case ABNORMAL_DURATION = 'abnormal_duration';

    public function category(): DriverEvaluationEventCategory
    {
        return match ($this) {
            self::SPEEDING,
            self::HARSH_BRAKING,
            self::HARSH_ACCELERATION,
            self::SUDDEN_STOP,
            self::HARSH_CORNERING => DriverEvaluationEventCategory::SAFETY,

            self::EXCESSIVE_IDLE,
            self::PROLONGED_STOP,
            self::WORKFLOW_VIOLATION => DriverEvaluationEventCategory::OPERATIONAL,

            self::ROUTE_DEVIATION => DriverEvaluationEventCategory::ROUTE_COMPLIANCE,

            self::LATE_DEPARTURE,
            self::LATE_ARRIVAL,
            self::ABNORMAL_DURATION => DriverEvaluationEventCategory::TIMING,
        };
    }

    public function defaultSeverity(): DriverEvaluationEventSeverity
    {
        return match ($this) {
            self::SPEEDING,
            self::HARSH_BRAKING,
            self::SUDDEN_STOP,
            self::ROUTE_DEVIATION => DriverEvaluationEventSeverity::HIGH,

            self::HARSH_ACCELERATION,
            self::WORKFLOW_VIOLATION,
            self::ABNORMAL_DURATION => DriverEvaluationEventSeverity::MEDIUM,

            self::EXCESSIVE_IDLE,
            self::PROLONGED_STOP,
            self::LATE_DEPARTURE,
            self::LATE_ARRIVAL => DriverEvaluationEventSeverity::LOW,

            self::HARSH_CORNERING => DriverEvaluationEventSeverity::MEDIUM,
        };
    }

    /**
     * Supported by DRIVER-EVAL-01 repository evidence for future detection.
     */
    public function isCurrentlySupported(): bool
    {
        return match ($this) {
            self::HARSH_CORNERING => false,
            default => true,
        };
    }

    public function isLiveDetectable(): bool
    {
        return match ($this) {
            self::SPEEDING,
            self::HARSH_BRAKING,
            self::HARSH_ACCELERATION,
            self::SUDDEN_STOP,
            self::ROUTE_DEVIATION,
            self::EXCESSIVE_IDLE,
            self::PROLONGED_STOP => true,
            default => false,
        };
    }

    public function isReplayDetectable(): bool
    {
        return match ($this) {
            self::HARSH_CORNERING => false,
            default => true,
        };
    }

    /**
     * Compact evidence contract description for documentation/tests.
     *
     * @return list<string>
     */
    public function evidenceRequirements(): array
    {
        return match ($this) {
            self::SPEEDING => ['tracking_logs.speed', 'threshold_policy', 'assignment_attribution'],
            self::HARSH_BRAKING,
            self::HARSH_ACCELERATION,
            self::SUDDEN_STOP => ['tracking_logs.speed', 'recorded_at_delta', 'assignment_attribution'],
            self::EXCESSIVE_IDLE,
            self::PROLONGED_STOP => ['tracking_logs', 'movement_or_speed_series', 'idle_policy'],
            self::ROUTE_DEVIATION => ['planned_route_geometry', 'tracking_position', 'deviation_threshold'],
            self::LATE_DEPARTURE,
            self::LATE_ARRIVAL,
            self::ABNORMAL_DURATION => ['assignment_timestamps', 'shipment_planned_dates_or_eta'],
            self::WORKFLOW_VIOLATION => ['assignment_execution_phase', 'status_transitions', 'pod'],
            self::HARSH_CORNERING => ['lateral_or_heading_rate_stream'], // unavailable today
        };
    }
}
