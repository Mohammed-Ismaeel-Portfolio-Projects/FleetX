<?php

namespace App\Simulation\Domain\Telemetry\Validators;

use App\Simulation\Domain\Telemetry\DTOs\TelemetrySnapshot;

/**
 * TelemetryValidator.
 * Validates telemetry snapshot values.
 * Never modifies data, only returns validation result.
 */
class TelemetryValidator
{
    /**
     * Validate telemetry snapshot.
     */
    public function validate(TelemetrySnapshot $snapshot): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Validate speed
        if ($snapshot->currentSpeed < 0) {
            $errors[] = 'Speed cannot be negative';
        }
        if ($snapshot->currentSpeed > 500) {
            $warnings[] = 'Speed exceeds realistic maximum';
        }

        // Validate RPM
        if ($snapshot->rpm < 0) {
            $errors[] = 'RPM cannot be negative';
        }
        if ($snapshot->rpm > 10000) {
            $warnings[] = 'RPM exceeds realistic maximum';
        }

        // Validate gear
        if ($snapshot->gear < -1) {
            $errors[] = 'Gear cannot be less than -1 (reverse)';
        }
        if ($snapshot->gear > 10) {
            $warnings[] = 'Gear exceeds typical maximum';
        }

        // Validate throttle
        if ($snapshot->throttlePosition < 0 || $snapshot->throttlePosition > 100) {
            $errors[] = 'Throttle position must be between 0 and 100';
        }

        // Validate engine load
        if ($snapshot->engineLoad < 0 || $snapshot->engineLoad > 100) {
            $errors[] = 'Engine load must be between 0 and 100';
        }

        // Validate fuel level
        if ($snapshot->fuelLevel < 0 || $snapshot->fuelLevel > 100) {
            $errors[] = 'Fuel level must be between 0 and 100';
        }

        // Validate battery voltage
        if ($snapshot->batteryVoltage < 0) {
            $errors[] = 'Battery voltage cannot be negative';
        }
        if ($snapshot->batteryVoltage > 15) {
            $warnings[] = 'Battery voltage exceeds typical maximum';
        }
        if ($snapshot->batteryVoltage < 10) {
            $warnings[] = 'Battery voltage below typical minimum';
        }

        return new ValidationResult(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings,
        );
    }
}

/**
 * ValidationResult DTO.
 */
readonly class ValidationResult
{
    public function __construct(
        public bool $isValid,
        public array $errors,
        public array $warnings,
    ) {}
}
