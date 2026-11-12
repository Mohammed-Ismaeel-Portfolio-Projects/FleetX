<?php

namespace App\Console\Commands;

use Database\Seeders\ClientC59E2ESeeder;
use Illuminate\Console\Command;

/**
 * Seeds deterministic Client App C5.9 runtime fixtures (no auth bypass).
 */
class SeedClientC59E2ECommand extends Command
{
    protected $signature = 'fleetx:c59-seed';

    protected $description = 'Seed deterministic Client App C5.9 E2E fixtures (client user, shipment, request, notification, conversation)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => ClientC59E2ESeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
