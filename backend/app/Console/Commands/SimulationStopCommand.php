<?php

namespace App\Console\Commands;

use App\Simulation\Session\SimulationSessionManager;
use Illuminate\Console\Command;

class SimulationStopCommand extends Command
{
    protected $signature = 'simulation:stop';

    protected $description = 'Stop the active simulation session';

    public function __construct(
        private SimulationSessionManager $sessionManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $session = $this->sessionManager->stop();
            $this->info("Stopped session {$session->sessionId}");
            $this->line("Ticks completed: {$session->tickCount}");
            $this->line('Uptime: ' . $session->uptimeSeconds() . 's');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
