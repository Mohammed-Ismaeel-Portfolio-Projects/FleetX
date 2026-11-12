<?php

namespace App\Console\Commands;

use App\Simulation\Session\SimulationSessionManager;
use Illuminate\Console\Command;

class SimulationResumeCommand extends Command
{
    protected $signature = 'simulation:resume';

    protected $description = 'Resume a paused simulation session';

    public function __construct(
        private SimulationSessionManager $sessionManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $session = $this->sessionManager->resume();
            $this->info("Resumed session {$session->sessionId}");
            $this->line("Status: {$session->status}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
