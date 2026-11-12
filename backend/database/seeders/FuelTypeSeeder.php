<?php

namespace Database\Seeders;

use App\Models\FuelType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FuelTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Diesel' => 'Standard diesel fuel for heavy freight vehicles.',
            'LNG' => 'Liquefied natural gas for long-haul lower-emission freight.',
            'CNG' => 'Compressed natural gas for urban and regional freight operations.',
            'Electric' => 'Battery electric freight vehicle.',
            'Hybrid' => 'Hybrid powertrain for reduced fuel consumption.',
        ];

        foreach ($types as $name => $description) {
            FuelType::updateOrCreate(
                ['code' => Str::upper(Str::slug($name, '_'))],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }
    }
}
