<?php

namespace App\Simulation\Driver;

/**
 * DriverDecisionSnapshot
 *
 * Immutable driver decision for one tick — desired target speed only.
 */
readonly class DriverDecisionSnapshot
{
    public function __construct(
        public string $vehicleId,
        public DriverProfile $driverProfile,
        public float $environmentRecommendedSpeed,
        public float $desiredTargetSpeed,
        public float $speedBias,
        public float $complianceLevel,
        public float $reactionFactor,
        public string $riskLevel,
        public string $timestamp,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'driver_profile' => $this->driverProfile->value,
            'environment_recommended_speed' => $this->environmentRecommendedSpeed,
            'desired_target_speed' => $this->desiredTargetSpeed,
            'speed_bias' => $this->speedBias,
            'compliance_level' => $this->complianceLevel,
            'reaction_factor' => $this->reactionFactor,
            'risk_level' => $this->riskLevel,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toTelemetryReference(): array
    {
        return [
            'driver_profile' => $this->driverProfile->value,
            'environment_recommended_speed' => $this->environmentRecommendedSpeed,
            'desired_target_speed' => $this->desiredTargetSpeed,
            'compliance_level' => $this->complianceLevel,
            'risk_level' => $this->riskLevel,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $profileValue = (string) ($data['driver_profile'] ?? DriverProfile::Normal->value);
        $profile = DriverProfile::tryFrom($profileValue) ?? DriverProfile::Normal;

        return new self(
            vehicleId: (string) ($data['vehicle_id'] ?? ''),
            driverProfile: $profile,
            environmentRecommendedSpeed: (float) ($data['environment_recommended_speed'] ?? 0),
            desiredTargetSpeed: (float) ($data['desired_target_speed'] ?? 0),
            speedBias: (float) ($data['speed_bias'] ?? 0),
            complianceLevel: (float) ($data['compliance_level'] ?? 0.8),
            reactionFactor: (float) ($data['reaction_factor'] ?? 1.0),
            riskLevel: (string) ($data['risk_level'] ?? 'medium'),
            timestamp: (string) ($data['timestamp'] ?? now()->toIso8601String()),
        );
    }
}
