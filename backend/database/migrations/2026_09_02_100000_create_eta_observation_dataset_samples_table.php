<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eta_observation_dataset_samples', function (Blueprint $table) {
            $table->id();
            $table->uuid('shipment_id');
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->unsignedBigInteger('tracking_log_id')->nullable();
            $table->timestamp('observation_time');

            // Baseline & Route Features (at observation time)
            $table->unsignedInteger('base_eta_seconds')->nullable();
            $table->unsignedBigInteger('base_distance_meters')->nullable();
            $table->string('baseline_routing_provider', 50)->nullable();
            $table->decimal('remaining_distance_meters', 12, 2)->nullable();
            $table->decimal('total_distance_meters', 12, 2)->nullable();
            $table->decimal('distance_travelled_meters', 12, 2)->nullable();
            $table->decimal('completion_percentage', 5, 2)->default(0.00);
            $table->unsignedInteger('segment_index')->nullable();
            $table->unsignedInteger('polyline_index')->nullable();

            // Dynamic Telemetry (at observation time)
            $table->decimal('current_latitude', 10, 7);
            $table->decimal('current_longitude', 10, 7);
            $table->decimal('current_speed_kmh', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->decimal('gps_accuracy', 8, 2)->nullable();
            $table->string('movement_status', 30)->nullable();

            // Driver Features
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->integer('driver_years_experience')->nullable();
            $table->decimal('driver_score', 5, 2)->nullable();

            // Vehicle / Cargo Features
            $table->uuid('vehicle_id')->nullable();
            $table->integer('vehicle_gross_weight_kg')->nullable();
            $table->decimal('cargo_weight', 10, 2)->nullable();
            $table->string('cargo_type', 100)->nullable();
            $table->boolean('temperature_controlled')->default(false);
            $table->boolean('hazardous_cargo')->default(false);

            // Temporal Features (derived ONLY from observation_time t)
            $table->unsignedSmallInteger('hour_of_day');
            $table->unsignedSmallInteger('day_of_week');

            // Target Variables (GROUND TRUTH ONLY - FORBIDDEN AS MODEL INPUT FEATURES!)
            $table->unsignedInteger('actual_total_duration_seconds')->nullable();
            $table->decimal('actual_remaining_seconds', 12, 2);
            $table->decimal('baseline_remaining_seconds', 12, 2);
            $table->decimal('residual_seconds', 12, 2);

            $table->timestamps();

            // Foreign Key and Indexes
            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->index('shipment_id');
            $table->index('assignment_id');
            $table->index('observation_time');
            $table->index('driver_id');
            $table->index('vehicle_id');

            // Uniqueness strategy: avoid duplicate observation samples for the same shipment and timestamp
            $table->unique(['shipment_id', 'observation_time'], 'uniq_eta_obs_shipment_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eta_observation_dataset_samples');
    }
};
