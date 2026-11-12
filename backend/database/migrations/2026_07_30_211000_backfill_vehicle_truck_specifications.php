<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vehicles', 'height_m')) {
            return;
        }

        DB::table('vehicles')->orderBy('id')->chunkById(100, function ($vehicles): void {
            foreach ($vehicles as $vehicle) {
                if ($vehicle->height_m !== null
                    && $vehicle->width_m !== null
                    && $vehicle->length_m !== null
                    && !empty($vehicle->routing_class)
                ) {
                    continue;
                }

                $weight = (int) ($vehicle->gross_weight_kg ?? 40000);
                $routingClass = match (true) {
                    $weight > 0 && $weight <= 3500 => 'van',
                    $weight > 3500 && $weight <= 12000 => 'light_truck',
                    default => 'heavy_truck',
                };

                [$height, $width, $length] = match ($routingClass) {
                    'van' => [2.60, 2.10, 5.80],
                    'light_truck' => [3.00, 2.35, 8.20],
                    default => [4.00, 2.55, 16.50],
                };

                DB::table('vehicles')->where('id', $vehicle->id)->update([
                    'routing_class' => $vehicle->routing_class ?: $routingClass,
                    'height_m' => $vehicle->height_m ?? $height,
                    'width_m' => $vehicle->width_m ?? $width,
                    'length_m' => $vehicle->length_m ?? $length,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Non-destructive data migration — no rollback of filled dimensions.
    }
};
