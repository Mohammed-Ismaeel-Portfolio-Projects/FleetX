<?php

namespace App\Console\Commands;

use App\Services\TrackingCycle\TrackingCycleService;
use Illuminate\Console\Command;

class TrackingSimulatorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracking:simulate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate GPS telemetry for active fleet vehicles';

    /**
     * The tracking cycle service.
     */
    private TrackingCycleService $trackingCycleService;

    /**
     * Create a new command instance.
     */
    public function __construct(TrackingCycleService $trackingCycleService)
    {
        parent::__construct();
        $this->trackingCycleService = $trackingCycleService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting tracking cycle...');
        $this->newLine();

        $result = $this->trackingCycleService->executeCycle();

        $this->info($result->toLogString());
        $this->newLine();

        return Command::SUCCESS;
    }
}
