<?php

namespace App\Console\Commands;

use App\Models\TrackingSession;
use App\Services\Tracking\ShipmentTrackingEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TickTrackingSessionsCommand extends Command
{
    protected $signature = 'tracking:tick-sessions
                            {--duration=0 : Duration in seconds to run loop (0 = single tick)}
                            {--interval=2 : Sleep interval between ticks in seconds}';

    protected $description = 'Executes a telemetry tick for all ACTIVE shipment tracking sessions';

    public function __construct(
        private ShipmentTrackingEngine $trackingEngine
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $duration = (int) $this->option('duration');
        $interval = max(1, (int) $this->option('interval'));

        $startTime = time();

        do {
            $activeSessions = TrackingSession::where('status', TrackingSession::STATUS_ACTIVE)->get();
            $tickedCount = 0;

            foreach ($activeSessions as $session) {
                $lockKey = "tracking_session:{$session->id}:tick-lock";
                $lock = Cache::lock($lockKey, 4);

                if ($lock->get()) {
                    try {
                        $state = $this->trackingEngine->tick($session);
                        if ($state) {
                            $tickedCount++;
                        }
                    } finally {
                        $lock->release();
                    }
                }
            }

            if ($duration <= 0) {
                $this->info("Completed single tick for {$tickedCount} active tracking session(s).");
                break;
            }

            $elapsed = time() - $startTime;
            if ($elapsed >= $duration) {
                $this->info("Completed tracking loop after {$elapsed} second(s).");
                break;
            }

            sleep($interval);
        } while (true);

        return Command::SUCCESS;
    }
}
