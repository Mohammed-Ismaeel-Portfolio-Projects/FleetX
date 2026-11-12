<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration restores the shipment_id foreign key to assignments table.
     * Domain invariant: ONE Assignment MUST belong to ONE Shipment
     * 
     * This restores the original FleetX architecture where assignments execute shipments.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Add shipment_id foreign key back (UUID to match shipments.id type)
            $table->uuid('shipment_id')->after('id');
            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('restrict');
        });

        // Add index on shipment_id
        DB::statement('CREATE INDEX assignments_shipment_id_index ON assignments (shipment_id)');

        // Drop old partial unique indexes that did not include shipment
        DB::statement('DROP INDEX IF EXISTS assignments_driver_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_vehicle_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_trailer_id_active_unique');

        // Create new partial unique indexes including shipment
        // Ensure ONE active assignment per driver
        DB::statement('CREATE UNIQUE INDEX assignments_driver_id_active_unique ON assignments (driver_id) WHERE is_active = true AND deleted_at IS NULL');
        
        // Ensure ONE active assignment per vehicle
        DB::statement('CREATE UNIQUE INDEX assignments_vehicle_id_active_unique ON assignments (vehicle_id) WHERE is_active = true AND deleted_at IS NULL');
        
        // Ensure ONE active assignment per trailer
        DB::statement('CREATE UNIQUE INDEX assignments_trailer_id_active_unique ON assignments (trailer_id) WHERE is_active = true AND deleted_at IS NULL');
        
        // Ensure ONE active assignment per shipment (domain invariant)
        DB::statement('CREATE UNIQUE INDEX assignments_shipment_id_active_unique ON assignments (shipment_id) WHERE is_active = true AND deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial unique indexes
        DB::statement('DROP INDEX IF EXISTS assignments_driver_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_vehicle_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_trailer_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_shipment_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_shipment_id_index');

        Schema::table('assignments', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['shipment_id']);
            // Then drop the UUID column
            $table->dropColumn('shipment_id');
        });

        // Recreate old partial unique indexes without shipment
        DB::statement('CREATE UNIQUE INDEX assignments_driver_id_active_unique ON assignments (driver_id) WHERE is_active = true AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_vehicle_id_active_unique ON assignments (vehicle_id) WHERE is_active = true AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_trailer_id_active_unique ON assignments (trailer_id) WHERE is_active = true AND deleted_at IS NULL');
    }
};
