<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusesSeeder extends Seeder
{
    /**
     * Seed the statuses table.
     *
     * Statuses are uniquely identified by the composite key (name, type).
     * Idempotent: safe to run multiple times via updateOrCreate().
     */
    public function run(): void
    {
        $statuses = [
            // Shipment statuses are seeded exclusively by ShipmentStatusesSeeder
            // from App\Support\ShipmentStatus::all() (Title Case). Do not seed
            // legacy snake_case shipment names here.

            // Order
            ['name' => 'Pending', 'type' => 'order'],
            ['name' => 'Approved', 'type' => 'order'],
            ['name' => 'Rejected', 'type' => 'order'],
            ['name' => 'Cancelled', 'type' => 'order'],

            // Driver
            ['name' => 'Available', 'type' => 'driver'],
            ['name' => 'Busy', 'type' => 'driver'],
            ['name' => 'Offline', 'type' => 'driver'],

            // Truck
            ['name' => 'Available', 'type' => 'truck'],
            ['name' => 'In Service', 'type' => 'truck'],
            ['name' => 'Maintenance', 'type' => 'truck'],

            // Assignment
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Ready', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'Active', 'type' => 'assignment'],
            ['name' => 'Completed', 'type' => 'assignment'],
            ['name' => 'Cancelled', 'type' => 'assignment'],

            // Payment
            ['name' => 'Pending', 'type' => 'payment'],
            ['name' => 'Paid', 'type' => 'payment'],
            ['name' => 'Failed', 'type' => 'payment'],

            // Issue
            ['name' => 'Open', 'type' => 'issue'],
            ['name' => 'In Progress', 'type' => 'issue'],
            ['name' => 'Resolved', 'type' => 'issue'],

            // Report
            ['name' => 'Draft', 'type' => 'report'],
            ['name' => 'Generated', 'type' => 'report'],

            // Sync
            ['name' => 'Pending', 'type' => 'sync'],
            ['name' => 'Synced', 'type' => 'sync'],
            ['name' => 'Failed', 'type' => 'sync'],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['name' => $status['name'], 'type' => $status['type']],
                $status
            );
        }
    }
}
