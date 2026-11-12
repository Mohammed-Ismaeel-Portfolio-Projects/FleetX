<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('routing_class', 32)
                ->default('heavy_truck')
                ->after('vehicle_type_id')
                ->comment('Routing class: van|light_truck|heavy_truck');

            $table->decimal('height_m', 5, 2)
                ->nullable()
                ->after('gross_weight_kg');

            $table->decimal('width_m', 5, 2)
                ->nullable()
                ->after('height_m');

            $table->decimal('length_m', 5, 2)
                ->nullable()
                ->after('width_m');
        });

        // Backfill existing fleet rows with realistic EU freight envelopes.
        DB::table('vehicles')->orderBy('id')->chunkById(100, function ($vehicles): void {
            foreach ($vehicles as $vehicle) {
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
                    'routing_class' => $routingClass,
                    'height_m' => $height,
                    'width_m' => $width,
                    'length_m' => $length,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['routing_class', 'height_m', 'width_m', 'length_m']);
        });
    }
};
