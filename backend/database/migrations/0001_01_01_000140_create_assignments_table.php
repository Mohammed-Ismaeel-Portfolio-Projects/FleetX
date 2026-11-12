<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->foreignId('truck_id')->constrained('trucks')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('driver_id');
            $table->index('truck_id');
            $table->index('status_id');
            $table->index('assigned_by');
        });

        // ERD constraint: at most one active assignment per driver, shipment, and truck.
        DB::statement('CREATE UNIQUE INDEX assignments_driver_id_active_unique ON assignments (driver_id) WHERE ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_shipment_id_active_unique ON assignments (shipment_id) WHERE ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_truck_id_active_unique ON assignments (truck_id) WHERE ended_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
