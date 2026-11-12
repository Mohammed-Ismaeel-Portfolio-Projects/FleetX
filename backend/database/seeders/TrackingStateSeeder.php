<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\TrackingState;
use App\Services\TrackingSimulator\EuropeanFleetLocations;
use Illuminate\Database\Seeder;

class TrackingStateSeeder extends Seeder
{
    /**
     * Seed tracking states using EU logistics hubs only.
     * Never uses global random latitude/longitude.
     */
    public function run(): void
    {
        /** @var EuropeanFleetLocations $fleetLocations */
        $fleetLocations = app(EuropeanFleetLocations::class);

        $assignments = Assignment::query()
            ->with('shipment')
            ->inRandomOrder()
            ->limit(50)
            ->get();

        foreach ($assignments as $assignment) {
            $pickupCity = $assignment->shipment?->pickup_city;
            $point = $pickupCity
                ? $fleetLocations->pickLogisticsPointForCity($pickupCity)
                : $fleetLocations->pickDistributedLogisticsPoint();

            $movementStatuses = ['Idle', 'Moving', 'Stopped'];
            $movementStatus = $movementStatuses[array_rand($movementStatuses)];
            $isMoving = $movementStatus === 'Moving';

            TrackingState::create([
                'assignment_id' => $assignment->id,
                'shipment_id' => $assignment->shipment_id,
                'vehicle_id' => $assignment->vehicle_id,
                'driver_id' => $assignment->driver_id,
                'trailer_id' => $assignment->trailer_id,
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'speed' => $isMoving ? rand(3000, 9000) / 100 : 0,
                'heading' => rand(0, 35900) / 100,
                'accuracy' => rand(100, 1000) / 100,
                'movement_status' => $movementStatus,
                'online_status' => collect(['Online', 'Online', 'Online', 'Offline'])->random(),
                'signal_quality' => collect(['Good', 'Good', 'Weak', 'Lost'])->random(),
                'last_update_at' => now()->subMinutes(rand(0, 60)),
            ]);
        }

        echo '✓ Seeded EU logistics tracking states for ' . $assignments->count() . " assignments\n";
    }
}
