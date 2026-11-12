<?php

namespace App\Simulation\Driver;

use App\Simulation\Environment\EnvironmentSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * DriverIntelligenceEngine
 *
 * Adapts EnvironmentSnapshot recommended speed into a desired target speed
 * using a DriverProfile. No GPS, routing, or DB persistence.
 */
class DriverIntelligenceEngine
{
    public const CACHE_PREFIX = 'simulation:driver:';

    private const CACHE_TTL_SECONDS = 86400;

    /**
     * @param 'moving'|'idle'|'stopped'|'offline' $vehicleState
     */
    public function decide(
        string $vehicleId,
        DriverProfile $profile,
        EnvironmentSnapshot $environment,
        string $vehicleState,
        ?string $timestamp = null,
    ): DriverDecisionSnapshot {
        $timestamp ??= now()->toIso8601String();
        $params = $profile->parameters();
        $envRecommended = max(0.0, $environment->recommendedTargetSpeedKmh);
        $roadLimit = max(0.0, $environment->roadSpeedLimitKmh);

        if ($vehicleState !== 'moving') {
            $decision = new DriverDecisionSnapshot(
                vehicleId: $vehicleId,
                driverProfile: $profile,
                environmentRecommendedSpeed: $envRecommended,
                desiredTargetSpeed: 0.0,
                speedBias: $params['speed_bias'],
                complianceLevel: $params['compliance'],
                reactionFactor: $params['reaction_factor'],
                riskLevel: $params['risk_level'],
                timestamp: $timestamp,
            );
            $this->store($decision);
            $this->logDecision($decision);

            return $decision;
        }

        $desired = $this->applyProfileRules(
            $profile,
            $params,
            $envRecommended,
            $roadLimit,
            $environment
        );

        // Smooth adaptation toward previous desired (eco / defensive stronger).
        $previous = $this->load($vehicleId);
        if ($previous !== null && $previous->desiredTargetSpeed > 0) {
            $blend = match ($profile) {
                DriverProfile::Eco => 0.45,
                DriverProfile::Defensive => 0.40,
                DriverProfile::Normal => 0.25,
                DriverProfile::Aggressive => 0.15,
            };
            $desired = ($previous->desiredTargetSpeed * $blend) + ($desired * (1.0 - $blend));
        }

        $desired = max(0.0, round($desired, 2));

        $decision = new DriverDecisionSnapshot(
            vehicleId: $vehicleId,
            driverProfile: $profile,
            environmentRecommendedSpeed: $envRecommended,
            desiredTargetSpeed: $desired,
            speedBias: $params['speed_bias'],
            complianceLevel: $params['compliance'],
            reactionFactor: $params['reaction_factor'],
            riskLevel: $params['risk_level'],
            timestamp: $timestamp,
        );

        $this->store($decision);
        $this->logDecision($decision);

        return $decision;
    }

    public function load(string $vehicleId): ?DriverDecisionSnapshot
    {
        $data = Cache::get(self::CACHE_PREFIX . $vehicleId);
        if (!is_array($data)) {
            return null;
        }

        return DriverDecisionSnapshot::fromArray($data);
    }

    public function store(DriverDecisionSnapshot $decision): void
    {
        Cache::put(
            self::CACHE_PREFIX . $decision->vehicleId,
            $decision->toArray(),
            self::CACHE_TTL_SECONDS
        );
    }

    /**
     * @param array{
     *     compliance: float,
     *     speed_bias: float,
     *     reaction_factor: float,
     *     risk_level: string,
     *     max_over_recommended: float,
     *     max_over_limit: float
     * } $params
     */
    private function applyProfileRules(
        DriverProfile $profile,
        array $params,
        float $envRecommended,
        float $roadLimit,
        EnvironmentSnapshot $environment,
    ): float {
        $bias = $params['speed_bias'];
        $compliance = $params['compliance'];

        // Blend: high compliance hugs environment; low compliance leans on bias.
        $biased = $envRecommended * (1.0 + $bias);
        $desired = ($envRecommended * $compliance) + ($biased * (1.0 - $compliance));

        $ceilingFromRecommended = $envRecommended * (1.0 + $params['max_over_recommended']);
        $ceilingFromLimit = $roadLimit * (1.0 + $params['max_over_limit']);

        switch ($profile) {
            case DriverProfile::Eco:
                // High compliance, never exceed road limit, stay at/under recommended.
                $desired = min($envRecommended * (1.0 + $bias), $roadLimit, $envRecommended);
                break;

            case DriverProfile::Normal:
                $desired = min($desired, $ceilingFromRecommended, $roadLimit);
                break;

            case DriverProfile::Aggressive:
                $desired = min(
                    max($desired, $envRecommended * (1.0 + $bias * 0.5)),
                    $ceilingFromRecommended,
                    $ceilingFromLimit
                );
                break;

            case DriverProfile::Defensive:
                $desired = min($envRecommended * (1.0 + $bias), $roadLimit, $envRecommended);
                $desired *= $this->adverseConditionFactor($environment);
                break;
        }

        return max(0.0, $desired);
    }

    private function adverseConditionFactor(EnvironmentSnapshot $environment): float
    {
        $factor = 1.0;

        if (in_array($environment->weatherCondition, ['rain', 'fog', 'snow'], true)) {
            $factor *= 0.88;
        }
        if (in_array($environment->roadCondition, ['wet', 'icy'], true)) {
            $factor *= 0.90;
        }
        if ($environment->accidentNearby || $environment->constructionZone) {
            $factor *= 0.75;
        }
        if ($environment->roadClosed) {
            $factor *= 0.0;
        }
        if ($environment->schoolZone) {
            $factor *= 0.70;
        }
        if (($environment->trafficDensity ?? 0) > 0.7) {
            $factor *= 0.85;
        }

        return max(0.0, min(1.0, $factor));
    }

    private function logDecision(DriverDecisionSnapshot $decision): void
    {
        Log::info('Driver decision', [
            'vehicle_id' => $decision->vehicleId,
            'driver_profile' => $decision->driverProfile->value,
            'environment_recommended_speed' => round($decision->environmentRecommendedSpeed, 2),
            'desired_target_speed' => round($decision->desiredTargetSpeed, 2),
            'compliance' => round($decision->complianceLevel, 2),
        ]);
    }
}
