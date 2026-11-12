<?php

namespace Database\Seeders;

use App\Models\Status;
use App\Models\Trailer;
use Database\Factories\TrailerFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class TrailerSeeder extends Seeder
{
    public function run(): void
    {
        // Create trailer statuses
        $this->createTrailerStatuses();

        Trailer::withTrashed()->get()->each->forceDelete();
        fake()->unique(true);

        $availableStatusId = TrailerFactory::trailerStatusId('Available');
        $inactiveStatusId = TrailerFactory::trailerStatusId('Inactive');

        // Create active trailers
        Trailer::factory()
            ->count(100)
            ->active($availableStatusId)
            ->state($this->trailerSequence(0))
            ->create();

        // Create inactive trailers
        Trailer::factory()
            ->count(25)
            ->inactive($inactiveStatusId)
            ->state($this->trailerSequence(100))
            ->create();

        $this->command?->info('Successfully seeded 125 realistic heavy logistics trailers.');
    }

    private function createTrailerStatuses(): void
    {
        $statuses = [
            ['name' => 'Available', 'type' => 'trailer'],
            ['name' => 'In Use', 'type' => 'trailer'],
            ['name' => 'Maintenance', 'type' => 'trailer'],
            ['name' => 'Inactive', 'type' => 'trailer'],
            ['name' => 'Retired', 'type' => 'trailer'],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate($status);
        }
    }

    private function trailerSequence(int $offset): Sequence
    {
        return new Sequence(function (Sequence $sequence) use ($offset): array {
            $index = $offset + $sequence->index + 1;

            return [
                'trailer_code' => sprintf('TR-%05d', $index),
                'fleet_number' => sprintf('FT-%04d', $index),
                'plate_number' => sprintf('FT-%05d', $index),
            ];
        });
    }
}
