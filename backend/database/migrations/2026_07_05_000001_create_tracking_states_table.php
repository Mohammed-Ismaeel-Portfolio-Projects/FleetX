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
        Schema::create('tracking_states', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign keys to related entities
            $table->unsignedBigInteger('assignment_id')->unique(); // Unique: one state per assignment
            $table->uuid('shipment_id');
            $table->uuid('vehicle_id');
            $table->unsignedBigInteger('driver_id');
            $table->uuid('trailer_id')->nullable();

            // Current location (latest GPS coordinates)
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Current telemetry
            $table->decimal('speed', 8, 2)->default(0);
            $table->decimal('heading', 8, 2)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();

            // Current operational state
            $table->enum('movement_status', ['Idle', 'Moving', 'Stopped'])->default('Idle');
            $table->enum('online_status', ['Online', 'Offline'])->default('Offline');
            $table->enum('signal_quality', ['Good', 'Weak', 'Lost'])->default('Lost');

            // Timestamp tracking
            $table->timestamp('last_update_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['shipment_id']);
            $table->index(['vehicle_id']);
            $table->index(['driver_id']);
            $table->index(['movement_status']);
            $table->index(['online_status']);
            $table->index(['last_update_at']);

            // Foreign key constraints
            $table->foreign('assignment_id')
                ->references('id')
                ->on('assignments')
                ->onDelete('cascade');

            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->onDelete('cascade');

            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->onDelete('cascade');

            $table->foreign('driver_id')
                ->references('id')
                ->on('drivers')
                ->onDelete('cascade');

            $table->foreign('trailer_id')
                ->references('id')
                ->on('trailers')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_states');
    }
};
