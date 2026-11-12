<?php

namespace App\Simulation\Domain\Environment\Services;

use App\Simulation\Domain\Environment\Models\TrafficCondition;
use App\Simulation\Core\SimulationClock;

/**
 * TrafficDensityCalculator Service.
 * Calculates traffic density from simulation time and scenario.
 */
class TrafficDensityCalculator
{
    private string $scenario = 'normal'; // normal, rush_hour, congested, blocked

    /**
     * Calculate traffic density.
     */
    public function calculate(SimulationClock $clock): TrafficCondition
    {
        $time = $clock->getCurrentTime();
        $hour = (int)$time->format('H');

        // TODO: Implement realistic traffic density calculation
        // This is architecture preparation only
        
        return match($this->scenario) {
            'rush_hour' => $this->calculateRushHourTraffic($hour),
            'congested' => $this->calculateCongestedTraffic($hour),
            'blocked' => TrafficCondition::blocked(),
            default => $this->calculateNormalTraffic($hour),
        };
    }

    /**
     * Calculate normal traffic.
     */
    private function calculateNormalTraffic(int $hour): TrafficCondition
    {
        // Low traffic during off-peak hours
        if ($hour >= 10 && $hour <= 15) {
            return TrafficCondition::low();
        }
        if ($hour >= 20 || $hour <= 6) {
            return TrafficCondition::low();
        }
        return TrafficCondition::medium();
    }

    /**
     * Calculate rush hour traffic.
     */
    private function calculateRushHourTraffic(int $hour): TrafficCondition
    {
        // Heavy traffic during rush hours
        if (($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19)) {
            return TrafficCondition::heavy();
        }
        return TrafficCondition::medium();
    }

    /**
     * Calculate congested traffic.
     */
    private function calculateCongestedTraffic(int $hour): TrafficCondition
    {
        // Heavy traffic most of the day
        if ($hour >= 6 && $hour <= 22) {
            return TrafficCondition::heavy();
        }
        return TrafficCondition::medium();
    }

    /**
     * Set scenario.
     */
    public function setScenario(string $scenario): void
    {
        $this->scenario = $scenario;
    }

    /**
     * Get scenario.
     */
    public function getScenario(): string
    {
        return $this->scenario;
    }
}
