<?php

namespace App\Console\Commands;

use App\Simulation\Session\SimulationSessionManager;
use Illuminate\Console\Command;

class SimulationStatusCommand extends Command
{
    protected $signature = 'simulation:status';

    protected $description = 'Show current simulation session status';

    public function __construct(
        private SimulationSessionManager $sessionManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $session = $this->sessionManager->status();

        if ($session === null) {
            $this->warn('No simulation session found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['session_id', $session->sessionId],
                ['status', $session->status],
                ['started_at', $session->startedAt],
                ['tick_count', $session->tickCount],
                ['tick_interval_seconds', $session->tickIntervalSeconds],
                ['time_scale', $session->timeScale . 'x'],
                ['effective_wait_seconds', round($session->effectiveWaitSeconds(), 2)],
                ['last_tick', $session->lastTick ?? '-'],
                ['uptime_seconds', $session->uptimeSeconds()],
                ['pid', $session->pid ?? '-'],
                ['paused_at', $session->pausedAt ?? '-'],
                ['stopped_at', $session->stoppedAt ?? '-'],
            ]
        );

        return self::SUCCESS;
    }
}
