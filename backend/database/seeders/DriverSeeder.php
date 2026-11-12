<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Status;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        // Create driver statuses
        $this->createDriverStatuses();

        Driver::withTrashed()->get()->each->forceDelete();
        fake()->unique(true);

        $availableStatusId = DriverFactory::driverStatusId('Available');
        $busyStatusId = DriverFactory::driverStatusId('Busy');
        $offlineStatusId = DriverFactory::driverStatusId('Offline');

        // Create active drivers
        Driver::factory()
            ->count(80)
            ->active($availableStatusId)
            ->state($this->driverSequence(0))
            ->create();

        // Create busy drivers
        Driver::factory()
            ->count(30)
            ->active($busyStatusId)
            ->state($this->driverSequence(80))
            ->create();

        // Create inactive drivers
        Driver::factory()
            ->count(15)
            ->inactive($offlineStatusId)
            ->state($this->driverSequence(110))
            ->create();

        $this->command?->info('Successfully seeded 125 realistic FleetX drivers.');
    }

    protected function createDriverStatuses(): void
    {
        $statuses = [
            ['name' => 'Available', 'type' => 'driver'],
            ['name' => 'Busy', 'type' => 'driver'],
            ['name' => 'Offline', 'type' => 'driver'],
            ['name' => 'On Leave', 'type' => 'driver'],
            ['name' => 'Suspended', 'type' => 'driver'],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate(
                ['name' => $status['name'], 'type' => $status['type']],
                $status
            );
        }
    }

    protected function driverSequence(int $start): Sequence
    {
        $counter = $start;
        return new Sequence(
            function () use (&$counter) {
                $counter++;
                return ['employee_number' => sprintf('EMP-%05d', $counter)];
            }
        );
    }
}
