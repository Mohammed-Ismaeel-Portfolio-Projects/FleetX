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
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('shipment_order_id');
            $table->foreign('shipment_order_id')
                ->references('id')
                ->on('shipment_orders')
                ->cascadeOnDelete();

            $table->uuid('shipment_id')->nullable();
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->nullOnDelete();

            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->foreign('assignment_id')
                ->references('id')
                ->on('assignments')
                ->nullOnDelete();

            $table->unsignedBigInteger('driver_id')->nullable();
            $table->foreign('driver_id')
                ->references('id')
                ->on('drivers')
                ->nullOnDelete();

            $table->uuid('vehicle_id')->nullable();
            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->nullOnDelete();

            $table->string('status', 20)->default('INACTIVE');

            $table->decimal('speed_multiplier', 5, 2)->default(1.00);
            $table->decimal('forced_speed_kmh', 8, 2)->nullable();
            $table->boolean('forced_deceleration')->default(false);
            $table->boolean('forced_route_deviation')->default(false);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index('shipment_order_id');
            $table->index('assignment_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_sessions');
    }
};
