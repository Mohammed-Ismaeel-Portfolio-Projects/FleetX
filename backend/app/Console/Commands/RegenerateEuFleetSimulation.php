<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\TrackingState;
use App\Services\TrackingSimulator\EuropeanFleetLocations;
use App\Simulation\Runtime\SimulationRuntime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Regenerate FleetX simulation tracking data inside EU land territory only.
 */
class RegenerateEuFleetSimulation extends Command
{
    protected $signature = 'fleet:regenerate-eu-simulation
                            {--force : Skip confirmation}
                            {--cycles=3 : Number of tracking simulation cycles to run}
                            {--fix-shipments : Also rewrite non-EU shipment cities to EU hubs}';

    protected $description = 'Reset tracking positions to EU logistics hubs and re-run simulation cycles';

    public function handle(
        EuropeanFleetLocations $fleetLocations,
        SimulationRuntime $simulationRuntime
    ): int {
        $this->info('=== EU FLEET SIMULATION REGENERATION ===');
        $this->newLine();

        $stateCount = TrackingState::count();
        $activeAssignments = Assignment::active()->count();
        $enrolledAssignments = \App\Simulation\Enrollment\SimulationEligibleAssignments::query()->count();

        $this->line("Tracking states: {$stateCount}");
        $this->line("Active assignments: {$activeAssignments}");
        $this->line("Engine-enrolled assignments: {$enrolledAssignments}");
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('Regenerate all tracking positions to EU logistics hubs?')) {
            $this->warn('Cancelled.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($fleetLocations) {
            if ($this->option('fix-shipments')) {
                $this->fixShipmentCities($fleetLocations);
            }

            // Clear tracking telemetry so simulator starts from EU hubs
            TrackingLog::query()->delete();
            TrackingState::query()->delete();

            $this->seedEuTrackingStates($fleetLocations);
        });

        $cycles = max(1, (int) $this->option('cycles'));
        $this->info("Running {$cycles} simulation cycle(s)...");

        for ($i = 1; $i <= $cycles; $i++) {
            $result = $simulationRuntime->runSingleCycle();
            $this->line(sprintf(
                '  Cycle %d: moved=%d idle=%d stopped=%d offline=%d logs=%d',
                $i,
                $result['vehicles_moved'],
                $result['idle_vehicles'],
                $result['stopped_vehicles'],
                $result['offline_vehicles'],
                $result['tracking_logs_created']
            ));
        }

        $this->newLine();
        $this->validateFleet($fleetLocations);

        return self::SUCCESS;
    }

    private function fixShipmentCities(EuropeanFleetLocations $fleetLocations): void
    {
        $updated = 0;
        Shipment::query()->chunkById(100, function ($shipments) use ($fleetLocations, &$updated) {
            foreach ($shipments as $shipment) {
                $pickup = $fleetLocations->resolveCity((string) $shipment->pickup_city);
                $delivery = $fleetLocations->resolveCity((string) $shipment->delivery_city);

                $dirty = false;
                if ($shipment->pickup_city !== $pickup['city'] || $shipment->pickup_country !== $pickup['country']) {
                    $shipment->pickup_city = $pickup['city'];
                    $shipment->pickup_country = $pickup['country'];
                    $dirty = true;
                }
                if ($shipment->delivery_city !== $delivery['city'] || $shipment->delivery_country !== $delivery['country']) {
                    // Ensure pickup != delivery when both resolve to same hub
                    if ($delivery['city'] === $pickup['city']) {
                        $delivery = $fleetLocations->pickNearbyDestination($pickup['city']);
                    }
                    $shipment->delivery_city = $delivery['city'];
                    $shipment->delivery_country = $delivery['country'];
                    $dirty = true;
                }

                if ($dirty) {
                    $shipment->save();
                    $updated++;
                }
            }
        });

        $this->info("Normalized {$updated} shipment city/country pairs to EU hubs.");
    }

    private function seedEuTrackingStates(EuropeanFleetLocations $fleetLocations): void
    {
        $assignments = Assignment::active()->with('shipment')->get();
        $created = 0;

        foreach ($assignments as $index => $assignment) {
            if (!$assignment->vehicle_id || !$assignment->shipment_id) {
                continue;
            }

            // Mix: ~70% at pickup (assigned/in-progress), ~30% distributed hubs
            if ($index % 10 < 7 && $assignment->shipment?->pickup_city) {
                $point = $fleetLocations->pickLogisticsPointForCity($assignment->shipment->pickup_city);
            } else {
                $point = $fleetLocations->pickDistributedLogisticsPoint();
            }

            $statuses = [
                ['movement' => 'Moving', 'online' => 'Online', 'signal' => 'Good', 'speed' => rand(40, 90)],
                ['movement' => 'Idle', 'online' => 'Online', 'signal' => 'Good', 'speed' => 0],
                ['movement' => 'Stopped', 'online' => 'Online', 'signal' => 'Weak', 'speed' => 0],
                ['movement' => 'Idle', 'online' => 'Offline', 'signal' => 'Lost', 'speed' => 0],
            ];
            $status = $statuses[$index % count($statuses)];

            TrackingState::create([
                'assignment_id' => $assignment->id,
                'shipment_id' => $assignment->shipment_id,
                'vehicle_id' => $assignment->vehicle_id,
                'driver_id' => $assignment->driver_id,
                'trailer_id' => $assignment->trailer_id,
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'speed' => $status['speed'],
                'heading' => rand(0, 359),
                'accuracy' => rand(3, 12),
                'movement_status' => $status['movement'],
                'online_status' => $status['online'],
                'signal_quality' => $status['signal'],
                'last_update_at' => now()->subMinutes(rand(0, 30)),
            ]);
            $created++;
        }

        $this->info("Created {$created} EU tracking states from active assignments.");
    }

    private function validateFleet(EuropeanFleetLocations $fleetLocations): void
    {
        $states = TrackingState::all();
        $total = $states->count();
        $invalid = 0;
        $outsideEu = 0;
        $onWater = 0;

        foreach ($states as $state) {
            $lat = (float) $state->latitude;
            $lng = (float) $state->longitude;

            if (!$fleetLocations->validateVehicleLocation($lat, $lng)) {
                $invalid++;
                $bounds = config('european_fleet.eu_bounds');
                $inBounds = $lat >= $bounds['min_lat'] && $lat <= $bounds['max_lat']
                    && $lng >= $bounds['min_lng'] && $lng <= $bounds['max_lng'];
                if (!$inBounds) {
                    $outsideEu++;
                } else {
                    $onWater++;
                }
            }
        }

        $this->info("Validation: {$total} tracking states");
        $this->info('  Valid EU land positions: ' . ($total - $invalid));
        $this->info("  Invalid: {$invalid} (outside EU: {$outsideEu}, water/far from hub: {$onWater})");

        if ($invalid === 0 && $total > 0) {
            $this->info('✓ All tracking positions are inside EU logistics land territory.');
        } elseif ($total === 0) {
            $this->warn('No tracking states found. Ensure active assignments exist, then re-run.');
        } else {
            $this->error('✗ Some positions failed EU validation.');
        }
    }
}
