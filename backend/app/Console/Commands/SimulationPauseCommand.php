<?php

namespace App\Console\Commands;

use App\Simulation\Session\SimulationSessionManager;
use Illuminate\Console\Command;

class SimulationPauseCommand extends Command
{
    protected $signature = 'simulation:pause';

    protected $description = 'Pause the active simulation session';

    public function __construct(
        private SimulationSessionManager $sessionManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $session = $this->sessionManager->pause();
            $this->info("Paused session {$session->sessionId}");
            $this->line("Ticks so far: {$session->tickCount}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
