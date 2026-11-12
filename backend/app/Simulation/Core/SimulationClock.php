<?php

namespace App\Simulation\Core;

/**
 * Simulation Clock for managing simulation time.
 * Handles time scaling and progression.
 */
class SimulationClock
{
    private \DateTime $currentTime;
    private float $timeScale = 1.0; // 1.0 = real-time, 10.0 = 10x speed
    private \DateTime $realTimeStart;

    public function __construct()
    {
        $this->currentTime = new \DateTime();
        $this->realTimeStart = new \DateTime();
    }

    /**
     * Advance simulation time by a duration.
     */
    public function advance(\DateInterval $interval): void
    {
        $this->currentTime->add($interval);
    }

    /**
     * Get current simulation time.
     */
    public function getCurrentTime(): \DateTime
    {
        return clone $this->currentTime;
    }

    /**
     * Set current simulation time.
     */
    public function setCurrentTime(\DateTime $time): void
    {
        $this->currentTime = clone $time;
    }

    /**
     * Get time scale.
     */
    public function getTimeScale(): float
    {
        return $this->timeScale;
    }

    /**
     * Set time scale.
     */
    public function setTimeScale(float $scale): void
    {
        $this->timeScale = max(0.1, $scale);
    }

    /**
     * Advance simulation time by milliseconds (scaled externally by caller if needed).
     */
    public function advanceMilliseconds(int $milliseconds): void
    {
        if ($milliseconds === 0) {
            return;
        }

        $target = clone $this->currentTime;
        // DateTime::modify supports fractional seconds via microseconds for PHP 8.2+
        $seconds = intdiv($milliseconds, 1000);
        $micros = ($milliseconds % 1000) * 1000;
        if ($seconds !== 0) {
            $target->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
        }
        if ($micros !== 0) {
            $target->modify(($micros >= 0 ? '+' : '') . $micros . ' microseconds');
        }
        $this->currentTime = $target;
    }

    /**
     * Reset clock to real-time.
     */
    public function reset(): void
    {
        $this->currentTime = new \DateTime();
        $this->realTimeStart = new \DateTime();
    }
}
