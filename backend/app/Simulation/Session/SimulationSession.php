<?php

namespace App\Simulation\Session;

/**
 * SimulationSession
 *
 * Immutable-ish session record for the orchestration layer.
 * Persisted to cache between artisan commands.
 */
class SimulationSession
{
    public const STATUS_IDLE = 'idle';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_STOPPED = 'stopped';

    /** @var list<float> */
    public const ALLOWED_TIME_SCALES = [1.0, 2.0, 5.0, 10.0];

    public function __construct(
        public string $sessionId,
        public string $startedAt,
        public string $status,
        public int $tickCount,
        public int $tickIntervalSeconds,
        public float $timeScale,
        public ?string $lastTick,
        public ?int $pid = null,
        public ?string $pausedAt = null,
        public ?string $stoppedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'started_at' => $this->startedAt,
            'status' => $this->status,
            'tick_count' => $this->tickCount,
            'tick_interval_seconds' => $this->tickIntervalSeconds,
            'time_scale' => $this->timeScale,
            'last_tick' => $this->lastTick,
            'pid' => $this->pid,
            'paused_at' => $this->pausedAt,
            'stopped_at' => $this->stoppedAt,
            'uptime_seconds' => $this->uptimeSeconds(),
            'effective_wait_seconds' => $this->effectiveWaitSeconds(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sessionId: (string) ($data['session_id'] ?? ''),
            startedAt: (string) ($data['started_at'] ?? now()->toIso8601String()),
            status: (string) ($data['status'] ?? self::STATUS_IDLE),
            tickCount: (int) ($data['tick_count'] ?? 0),
            tickIntervalSeconds: (int) ($data['tick_interval_seconds'] ?? 60),
            timeScale: (float) ($data['time_scale'] ?? 1.0),
            lastTick: isset($data['last_tick']) ? (string) $data['last_tick'] : null,
            pid: isset($data['pid']) ? (int) $data['pid'] : null,
            pausedAt: isset($data['paused_at']) ? (string) $data['paused_at'] : null,
            stoppedAt: isset($data['stopped_at']) ? (string) $data['stopped_at'] : null,
        );
    }

    public function uptimeSeconds(): int
    {
        try {
            $start = new \DateTimeImmutable($this->startedAt);
            $end = $this->stoppedAt
                ? new \DateTimeImmutable($this->stoppedAt)
                : new \DateTimeImmutable('now');

            return max(0, $end->getTimestamp() - $start->getTimestamp());
        } catch (\Throwable) {
            return 0;
        }
    }

    public function effectiveWaitSeconds(): float
    {
        $scale = max(0.1, $this->timeScale);

        return max(0.1, $this->tickIntervalSeconds / $scale);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_RUNNING, self::STATUS_PAUSED], true);
    }
}
