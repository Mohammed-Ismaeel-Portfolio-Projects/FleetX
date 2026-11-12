<?php

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\Company;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        // Create shipment statuses
        $this->createShipmentStatuses();

        // Clear existing shipments
        Shipment::withTrashed()->get()->each->forceDelete();
        fake()->unique(true);

        $draftStatusId = ShipmentFactory::shipmentStatusId('Draft');
        $pendingReviewStatusId = ShipmentFactory::shipmentStatusId('Pending Review');
        $approvedStatusId = ShipmentFactory::shipmentStatusId('Approved');
        $waitingAssignmentStatusId = ShipmentFactory::shipmentStatusId('Waiting Assignment');
        $assignedStatusId = ShipmentFactory::shipmentStatusId('Assigned');
        $readyForPickupStatusId = ShipmentFactory::shipmentStatusId('Ready For Pickup');
        $pickedUpStatusId = ShipmentFactory::shipmentStatusId('Picked Up');
        $inTransitStatusId = ShipmentFactory::shipmentStatusId('In Transit');
        $atCustomsStatusId = ShipmentFactory::shipmentStatusId('At Customs');
        $outForDeliveryStatusId = ShipmentFactory::shipmentStatusId('Out For Delivery');
        $deliveredStatusId = ShipmentFactory::shipmentStatusId('Delivered');
        $completedStatusId = ShipmentFactory::shipmentStatusId('Completed');
        $cancelledStatusId = ShipmentFactory::shipmentStatusId('Cancelled');

        // Get available companies
        $availableCompanies = Company::take(20)->get();
        $adminUser = User::where('email', 'admin@fleetx.com')->first();

        // Create draft shipments (8)
        for ($i = 0; $i < 8; $i++) {
            Shipment::factory()
                ->draft()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create pending review shipments (6)
        for ($i = 8; $i < 14; $i++) {
            Shipment::factory()
                ->pendingReview()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create approved shipments (5)
        for ($i = 14; $i < 19; $i++) {
            Shipment::factory()
                ->approved()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create waiting assignment shipments (7)
        for ($i = 19; $i < 26; $i++) {
            Shipment::factory()
                ->waitingAssignment()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create assigned shipments (6)
        for ($i = 26; $i < 32; $i++) {
            Shipment::factory()
                ->assigned()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create ready for pickup shipments (5)
        for ($i = 32; $i < 37; $i++) {
            Shipment::factory()
                ->readyForPickup()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create picked up shipments (8)
        for ($i = 37; $i < 45; $i++) {
            Shipment::factory()
                ->pickedUp()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create in transit shipments (10)
        for ($i = 45; $i < 55; $i++) {
            Shipment::factory()
                ->inTransit()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create at customs shipments (4)
        for ($i = 55; $i < 59; $i++) {
            Shipment::factory()
                ->atCustoms()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create out for delivery shipments (6)
        for ($i = 59; $i < 65; $i++) {
            Shipment::factory()
                ->outForDelivery()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create delivered shipments (8)
        for ($i = 65; $i < 73; $i++) {
            Shipment::factory()
                ->delivered()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create completed shipments (15)
        for ($i = 73; $i < 88; $i++) {
            Shipment::factory()
                ->completed()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        // Create cancelled shipments (5)
        for ($i = 88; $i < 93; $i++) {
            Shipment::factory()
                ->cancelled()
                ->forCustomer($availableCompanies[$i % $availableCompanies->count()]->id)
                ->create([
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]);
        }

        $this->command?->info('Successfully seeded 93 FleetX shipments across all statuses.');
    }

    protected function createShipmentStatuses(): void
    {
        foreach (ShipmentStatus::all() as $name) {
            Status::firstOrCreate(
                ['name' => $name, 'type' => ShipmentStatus::TYPE],
                ['name' => $name, 'type' => ShipmentStatus::TYPE]
            );
        }
    }
}
