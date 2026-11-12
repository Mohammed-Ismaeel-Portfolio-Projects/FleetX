<?php

namespace App\Console\Commands;

use App\Simulation\Session\SimulationSession;
use App\Simulation\Session\SimulationSessionManager;
use Illuminate\Console\Command;

class SimulationStartCommand extends Command
{
    protected $signature = 'simulation:start
                            {--interval=60 : Base tick interval in seconds}
                            {--scale=1 : Simulation speed 1|2|5|10}';

    protected $description = 'Start a continuous simulation session (loops SimulationRuntime.runSingleCycle)';

    public function __construct(
        private SimulationSessionManager $sessionManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $scale = (float) $this->option('scale');

        if (!in_array($scale, SimulationSession::ALLOWED_TIME_SCALES, true)) {
            $this->error('Invalid --scale. Allowed: 1, 2, 5, 10');

            return self::FAILURE;
        }

        $this->info('Starting simulation session...');
        $this->line("Interval: {$interval}s | Scale: {$scale}x | Wait: " . round($interval / $scale, 2) . 's between ticks');
        $this->line('Ctrl+C or run simulation:stop in another terminal to stop.');
        $this->newLine();

        try {
            $this->sessionManager->start(
                tickIntervalSeconds: $interval,
                timeScale: $scale,
                afterTick: function (SimulationSession $session, ?array $result): void {
                    $moved = $result['vehicles_moved'] ?? '-';
                    $logs = $result['tracking_logs_created'] ?? '-';
                    $this->line(sprintf(
                        '[tick #%d] moved=%s logs=%s wait=%.1fs scale=%sx',
                        $session->tickCount,
                        $moved,
                        $logs,
                        $session->effectiveWaitSeconds(),
                        $session->timeScale
                    ));
                }
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $final = $this->sessionManager->status();
        $this->newLine();
        $this->info('Session ended: ' . ($final?->status ?? 'unknown'));
        $this->line('Ticks: ' . ($final?->tickCount ?? 0));
        $this->line('Uptime: ' . ($final?->uptimeSeconds() ?? 0) . 's');

        return self::SUCCESS;
    }
}
