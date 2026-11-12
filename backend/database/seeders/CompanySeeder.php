<?php

namespace Database\Seeders;

use App\Models\Company;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $activeStatusId = CompanyFactory::companyStatusId('active');
        $inactiveStatusId = CompanyFactory::companyStatusId('inactive');

        Company::withTrashed()->get()->each->forceDelete();
        fake()->unique(true);

        Company::factory()
            ->count(80)
            ->active($activeStatusId)
            ->state($this->demoSequence(0))
            ->create();

        Company::factory()
            ->count(20)
            ->inactive($inactiveStatusId)
            ->state($this->demoSequence(80))
            ->create();

        $this->command?->info('Successfully seeded 100 realistic logistics companies.');
    }

    private function demoSequence(int $offset): Sequence
    {
        return new Sequence(function (Sequence $sequence) use ($offset): array {
            $index = $offset + $sequence->index;
            $location = CompanyFactory::locationForIndex($index);

            return [
                ...CompanyFactory::demoIdentity($index, $location['city']),
                'country' => $location['country'],
                'state_province' => $location['state'],
                'city' => $location['city'],
                'postal_code' => $location['postal'],
                'timezone' => $location['timezone'],
                'currency_code' => $location['currency'],
                'language_code' => $location['language'],
                'latitude' => fake()->latitude($location['lat'] - 0.08, $location['lat'] + 0.08),
                'longitude' => fake()->longitude($location['lng'] - 0.08, $location['lng'] + 0.08),
            ];
        });
    }
}
