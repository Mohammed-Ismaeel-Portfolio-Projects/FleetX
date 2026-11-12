<?php

namespace App\Simulation\Driver;

/**
 * DriverProfile
 *
 * Behavioural profile adapting environment recommended speed
 * into a desired target for Vehicle Dynamics.
 */
enum DriverProfile: string
{
    case Eco = 'eco';
    case Normal = 'normal';
    case Aggressive = 'aggressive';
    case Defensive = 'defensive';

    /**
     * Base behavioural parameters (before environment modifiers).
     *
     * @return array{
     *     compliance: float,
     *     speed_bias: float,
     *     reaction_factor: float,
     *     risk_level: string,
     *     max_over_recommended: float,
     *     max_over_limit: float
     * }
     */
    public function parameters(): array
    {
        return match ($this) {
            self::Eco => [
                'compliance' => 0.95,
                'speed_bias' => -0.08,
                'reaction_factor' => 0.75,
                'risk_level' => 'low',
                'max_over_recommended' => 0.0,
                'max_over_limit' => 0.0,
            ],
            self::Normal => [
                'compliance' => 0.80,
                'speed_bias' => 0.05,
                'reaction_factor' => 1.0,
                'risk_level' => 'medium',
                'max_over_recommended' => 0.08,
                'max_over_limit' => 0.0,
            ],
            self::Aggressive => [
                'compliance' => 0.45,
                'speed_bias' => 0.18,
                'reaction_factor' => 1.35,
                'risk_level' => 'high',
                'max_over_recommended' => 0.22,
                'max_over_limit' => 0.05, // may slightly exceed posted limit
            ],
            self::Defensive => [
                'compliance' => 0.92,
                'speed_bias' => -0.12,
                'reaction_factor' => 0.85,
                'risk_level' => 'low',
                'max_over_recommended' => 0.0,
                'max_over_limit' => 0.0,
            ],
        };
    }

    /**
     * Deterministic profile from driver / vehicle identity (no DB column).
     */
    public static function forIdentity(?string $driverId, string $vehicleId): self
    {
        $seed = abs(crc32(($driverId ?: 'no-driver') . '|' . $vehicleId));
        $profiles = self::cases();

        return $profiles[$seed % count($profiles)];
    }
}
