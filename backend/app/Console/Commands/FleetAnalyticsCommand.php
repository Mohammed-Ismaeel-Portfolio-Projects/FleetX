<?php

namespace App\Console\Commands;

use App\Simulation\Analytics\FleetAnalyticsEngine;
use Illuminate\Console\Command;

/**
 * Read-only KPI computation from simulation runtime caches.
 * Does not tick SimulationRuntime.
 */
class FleetAnalyticsCommand extends Command
{
    protected $signature = 'fleet:analytics {--fresh : Ignore TTL cache and recompute}';

    protected $description = 'Compute fleet KPI snapshots from telemetry and lifecycle caches';

    public function __construct(
        private FleetAnalyticsEngine $analyticsEngine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Computing fleet analytics...');

        $snapshot = $this->option('fresh')
            ? $this->analyticsEngine->compute()
            : $this->analyticsEngine->getOrCompute();

        $fleet = $snapshot->fleet;

        $this->table(
            ['KPI', 'Value'],
            [
                ['Active vehicles', $fleet['active_vehicles'] ?? 0],
                ['Moving', $fleet['moving_vehicles'] ?? 0],
                ['Idle', $fleet['idle_vehicles'] ?? 0],
                ['Active shipments', $fleet['active_shipments'] ?? 0],
                ['Avg speed (km/h)', $fleet['avg_speed_kmh'] ?? 0],
                ['Avg fuel %', $fleet['avg_fuel_level'] ?? 'n/a'],
                ['Avg engine health', $fleet['avg_engine_health'] ?? 'n/a'],
                ['Utilization %', $fleet['utilization_pct'] ?? 0],
                ['Avg route completion %', $fleet['avg_route_completion_pct'] ?? 0],
            ]
        );

        $this->info('Cached: simulation:kpi:fleet (+ vehicle/driver/shipment keys), TTL 5m');

        return self::SUCCESS;
    }
}
