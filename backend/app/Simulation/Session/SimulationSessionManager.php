<?php

namespace App\Simulation\Session;

use App\Simulation\Runtime\SimulationRuntime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SimulationSessionManager
 *
 * Orchestration-only layer. Continuously invokes
 * SimulationRuntime::runSingleCycle() — never SimulationEngine directly.
 */
class SimulationSessionManager
{
    public const CACHE_KEY = 'simulation:session:current';

    private const CACHE_TTL_SECONDS = 86400;

    private const POLL_WHILE_PAUSED_SECONDS = 1;

    public function __construct(
        private SimulationRuntime $simulationRuntime,
        private SimulationClock $clock,
    ) {}

    /**
     * Start a session and run the continuous tick loop (blocking).
     *
     * @param  callable(SimulationSession, array<string, mixed>|null): void|null  $afterTick
     */
    public function start(
        int $tickIntervalSeconds = 60,
        float $timeScale = 1.0,
        ?callable $afterTick = null,
    ): SimulationSession {
        $existing = $this->current();
        if ($existing !== null && $existing->isActive() && $this->processLooksAlive($existing)) {
            throw new \RuntimeException(
                "Simulation session already active ({$existing->status}): {$existing->sessionId}"
            );
        }

        $this->clock->setTickIntervalSeconds($tickIntervalSeconds);
        $this->clock->setTimeScale($timeScale);

        $session = new SimulationSession(
            sessionId: (string) Str::uuid(),
            startedAt: now()->toIso8601String(),
            status: SimulationSession::STATUS_RUNNING,
            tickCount: 0,
            tickIntervalSeconds: $tickIntervalSeconds,
            timeScale: $timeScale,
            lastTick: null,
            pid: getmypid() ?: null,
        );
        $this->persist($session);

        Log::info('Simulation session started', [
            'session_id' => $session->sessionId,
            'tick_interval_seconds' => $tickIntervalSeconds,
            'time_scale' => $timeScale,
        ]);

        $this->runLoop($afterTick);

        return $this->current() ?? $session;
    }

    public function pause(): SimulationSession
    {
        $session = $this->requireActiveSession();
        if ($session->status === SimulationSession::STATUS_PAUSED) {
            return $session;
        }

        $session->status = SimulationSession::STATUS_PAUSED;
        $session->pausedAt = now()->toIso8601String();
        $this->persist($session);

        Log::info('Simulation session paused', ['session_id' => $session->sessionId]);

        return $session;
    }

    public function resume(): SimulationSession
    {
        $session = $this->requireActiveSession();
        if ($session->status === SimulationSession::STATUS_RUNNING) {
            return $session;
        }

        $session->status = SimulationSession::STATUS_RUNNING;
        $session->pausedAt = null;
        $this->persist($session);

        Log::info('Simulation session resumed', ['session_id' => $session->sessionId]);

        return $session;
    }

    public function stop(): SimulationSession
    {
        $session = $this->current();
        if ($session === null) {
            throw new \RuntimeException('No simulation session found');
        }

        $session->status = SimulationSession::STATUS_STOPPED;
        $session->stoppedAt = now()->toIso8601String();
        $this->persist($session);

        Log::info('Simulation session stopped', [
            'session_id' => $session->sessionId,
            'tick_count' => $session->tickCount,
            'uptime_seconds' => $session->uptimeSeconds(),
        ]);

        return $session;
    }

    public function status(): ?SimulationSession
    {
        return $this->current();
    }

    public function current(): ?SimulationSession
    {
        $data = Cache::get(self::CACHE_KEY);
        if (!is_array($data)) {
            return null;
        }

        return SimulationSession::fromArray($data);
    }

    /**
     * Set time scale on the active session (1x / 2x / 5x / 10x).
     */
    public function setTimeScale(float $timeScale): SimulationSession
    {
        $session = $this->requireActiveSession();
        $this->clock->setTimeScale($timeScale);
        $session->timeScale = $timeScale;
        $this->persist($session);

        return $session;
    }

    /**
     * @param  callable(SimulationSession, array<string, mixed>|null): void|null  $afterTick
     */
    private function runLoop(?callable $afterTick): void
    {
        while (true) {
            $session = $this->current();
            if ($session === null) {
                break;
            }

            if ($session->status === SimulationSession::STATUS_STOPPED) {
                break;
            }

            if ($session->status === SimulationSession::STATUS_PAUSED) {
                sleep(self::POLL_WHILE_PAUSED_SECONDS);
                continue;
            }

            if ($session->status !== SimulationSession::STATUS_RUNNING) {
                break;
            }

            $this->clock->syncFromSession($session);

            $tickResult = null;
            try {
                // ONLY entry into the simulation stack for a tick.
                $tickResult = $this->simulationRuntime->runSingleCycle();
            } catch (\Throwable $e) {
                Log::error('Simulation session tick failed', [
                    'session_id' => $session->sessionId,
                    'tick_count' => $session->tickCount,
                    'error' => $e->getMessage(),
                ]);
            }

            // Re-load in case pause/stop arrived during the tick.
            $session = $this->current() ?? $session;
            if ($session->status === SimulationSession::STATUS_STOPPED) {
                break;
            }

            $session->tickCount++;
            $session->lastTick = now()->toIso8601String();
            $this->persist($session);

            if ($afterTick !== null) {
                $afterTick($session, $tickResult);
            }

            // Re-check before sleeping so stop is responsive.
            $session = $this->current() ?? $session;
            if ($session->status !== SimulationSession::STATUS_RUNNING) {
                continue;
            }

            $this->clock->syncFromSession($session);
            $this->clock->sleepUntilNextTick();
        }
    }

    private function requireActiveSession(): SimulationSession
    {
        $session = $this->current();
        if ($session === null || !$session->isActive()) {
            throw new \RuntimeException('No active simulation session (start one with simulation:start)');
        }

        return $session;
    }

    private function persist(SimulationSession $session): void
    {
        Cache::put(self::CACHE_KEY, $session->toArray(), self::CACHE_TTL_SECONDS);
    }

    private function processLooksAlive(SimulationSession $session): bool
    {
        if ($session->pid === null || $session->pid <= 0) {
            // No PID recorded — treat active status as alive.
            return true;
        }

        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $out = [];
            exec('tasklist /FI "PID eq ' . (int) $session->pid . '" 2>NUL', $out);
            $joined = implode("\n", $out);

            return str_contains($joined, (string) $session->pid);
        }

        return function_exists('posix_kill') && @posix_kill($session->pid, 0);
    }
}
