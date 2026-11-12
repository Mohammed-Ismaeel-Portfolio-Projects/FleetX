<?php

namespace App\Simulation\Session;

/**
 * SimulationClock (Session orchestration)
 *
 * Controls wall-clock wait between SimulationRuntime.runSingleCycle() calls.
 * Distinct from App\Simulation\Core\SimulationClock (in-engine sim time).
 */
class SimulationClock
{
    public function __construct(
        private int $tickIntervalSeconds = 60,
        private float $timeScale = 1.0,
    ) {
        $this->setTimeScale($timeScale);
        $this->setTickIntervalSeconds($tickIntervalSeconds);
    }

    public function setTickIntervalSeconds(int $seconds): void
    {
        $this->tickIntervalSeconds = max(1, $seconds);
    }

    public function setTimeScale(float $scale): void
    {
        $normalized = (float) $scale;
        $allowed = false;
        foreach (SimulationSession::ALLOWED_TIME_SCALES as $candidate) {
            if (abs($candidate - $normalized) < 0.001) {
                $allowed = true;
                $normalized = $candidate;
                break;
            }
        }

        if (!$allowed) {
            throw new \InvalidArgumentException(
                'timeScale must be one of: ' . implode(', ', SimulationSession::ALLOWED_TIME_SCALES)
            );
        }

        $this->timeScale = $normalized;
    }

    public function getTickIntervalSeconds(): int
    {
        return $this->tickIntervalSeconds;
    }

    public function getTimeScale(): float
    {
        return $this->timeScale;
    }

    /**
     * Wall-clock seconds to wait until the next tick at current scale.
     */
    public function waitSeconds(): float
    {
        return max(0.1, $this->tickIntervalSeconds / $this->timeScale);
    }

    /**
     * ISO timestamp for the next scheduled tick after $from.
     */
    public function nextTickAt(?\DateTimeInterface $from = null): string
    {
        $from ??= new \DateTimeImmutable('now');
        $seconds = (int) round($this->waitSeconds());

        return (new \DateTimeImmutable($from->format(\DateTimeInterface::ATOM)))
            ->modify("+{$seconds} seconds")
            ->format(\DateTimeInterface::ATOM);
    }

    /**
     * Sleep until the next tick is due (supports fractional seconds).
     */
    public function sleepUntilNextTick(): void
    {
        $wait = $this->waitSeconds();
        $whole = (int) floor($wait);
        $fraction = $wait - $whole;

        if ($whole > 0) {
            sleep($whole);
        }
        if ($fraction > 0.001) {
            usleep((int) round($fraction * 1_000_000));
        }
    }

    public function syncFromSession(SimulationSession $session): void
    {
        $this->setTickIntervalSeconds($session->tickIntervalSeconds);
        $this->setTimeScale($session->timeScale);
    }
}
