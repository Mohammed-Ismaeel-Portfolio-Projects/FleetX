<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Trailer;
use App\Support\EngineEnrollmentStatus;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        // Create assignment statuses
        $this->createAssignmentStatuses();

        // Clear existing assignments
        Assignment::withTrashed()->get()->each->forceDelete();
        fake()->unique(true);

        $draftStatusId = AssignmentFactory::assignmentStatusId('Draft');
        $assignedStatusId = AssignmentFactory::assignmentStatusId('Assigned');
        $readyStatusId = AssignmentFactory::assignmentStatusId('Ready');
        $inOperationStatusId = AssignmentFactory::assignmentStatusId('In Operation');
        $completedStatusId = AssignmentFactory::assignmentStatusId('Completed');
        $cancelledStatusId = AssignmentFactory::assignmentStatusId('Cancelled');

        // Get available assets - use all shipments, not just specific statuses
        $availableShipments = Shipment::take(55)->get();
        $availableDrivers = Driver::where('is_active', true)->take(50)->get();
        $availableVehicles = Vehicle::where('is_active', true)->take(50)->get();
        $availableTrailers = Trailer::where('is_active', true)->take(50)->get();
        
        // Ensure we have enough shipments for all assignments
        if ($availableShipments->count() < 55) {
            $this->command?->warn('Not enough shipments available. Skipping AssignmentSeeder.');
            return;
        }

        $adminUser = User::where('email', 'admin@fleetx.com')->first();

        // Create draft assignments (5) - use unique shipments
        for ($i = 0; $i < 5; $i++) {
            Assignment::factory()
                ->draft()
                ->forShipment($availableShipments[$i]->id)
                ->forDriver($availableDrivers[$i % $availableDrivers->count()]->id)
                ->forVehicle($availableVehicles[$i % $availableVehicles->count()]->id)
                ->forTrailer($availableTrailers[$i % $availableTrailers->count()]->id)
                ->create([
                    'assigned_by' => $adminUser?->id,
                ]);
        }

        // Create assigned assignments for Assigned shipments (indices 26-31)
        for ($i = 26; $i < 32 && $i < $availableShipments->count(); $i++) {
            Assignment::factory()
                ->assigned()
                ->forShipment($availableShipments[$i]->id)
                ->forDriver($availableDrivers[$i % $availableDrivers->count()]->id)
                ->forVehicle($availableVehicles[$i % $availableVehicles->count()]->id)
                ->forTrailer($availableTrailers[$i % $availableTrailers->count()]->id)
                ->create([
                    'assigned_by' => $adminUser?->id,
                ]);
        }

        // Create ready assignments for Ready For Pickup shipments (indices 32-36)
        for ($i = 32; $i < 37 && $i < $availableShipments->count(); $i++) {
            Assignment::factory()
                ->ready()
                ->forShipment($availableShipments[$i]->id)
                ->forDriver($availableDrivers[$i % $availableDrivers->count()]->id)
                ->forVehicle($availableVehicles[$i % $availableVehicles->count()]->id)
                ->forTrailer($availableTrailers[$i % $availableTrailers->count()]->id)
                ->create([
                    'assigned_by' => $adminUser?->id,
                ]);
        }

        // Create in-operation active assignments for Picked Up, In Transit, At Customs, Out For Delivery shipments (indices 37-64)
        for ($i = 37; $i < 65 && $i < $availableShipments->count(); $i++) {
            Assignment::factory()
                ->inOperation()
                ->engineEnrolled()
                ->forShipment($availableShipments[$i]->id)
                ->forDriver($availableDrivers[$i % $availableDrivers->count()]->id)
                ->forVehicle($availableVehicles[$i % $availableVehicles->count()]->id)
                ->forTrailer($availableTrailers[$i % $availableTrailers->count()]->id)
                ->create([
                    'assigned_by' => $adminUser?->id,
                ]);
        }

        // Create historical ended assignments for Delivered & Completed shipments (indices 65-87)
        for ($i = 65; $i < 88 && $i < $availableShipments->count(); $i++) {
            Assignment::factory()
                ->completed()
                ->forShipment($availableShipments[$i]->id)
                ->forDriver($availableDrivers[$i % $availableDrivers->count()]->id)
                ->forVehicle($availableVehicles[$i % $availableVehicles->count()]->id)
                ->forTrailer($availableTrailers[$i % $availableTrailers->count()]->id)
                ->create([
                    'assigned_by' => $adminUser?->id,
                ]);
        }

        // Create cancelled assignments for Cancelled shipments (indices 88-92)
        for ($i = 88; $i < 93 && $i < $availableShipments->count(); $i++) {
            Assignment::factory()
                ->cancelled()
                ->forShipment($availableShipments[$i]->id)
                ->forDriver($availableDrivers[$i % $availableDrivers->count()]->id)
                ->forVehicle($availableVehicles[$i % $availableVehicles->count()]->id)
                ->forTrailer($availableTrailers[$i % $availableTrailers->count()]->id)
                ->create([
                    'assigned_by' => $adminUser?->id,
                ]);
        }

        $this->command?->info('Successfully seeded FleetX assignments aligned with shipment lifecycles.');
    }

    protected function createAssignmentStatuses(): void
    {
        $statuses = [
            ['name' => 'Draft', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Ready', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'Completed', 'type' => 'assignment'],
            ['name' => 'Cancelled', 'type' => 'assignment'],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate(
                ['name' => $status['name'], 'type' => $status['type']],
                $status
            );
        }
    }
}
