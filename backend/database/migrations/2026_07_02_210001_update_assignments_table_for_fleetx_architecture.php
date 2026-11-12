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
        Schema::table('assignments', function (Blueprint $table) {
            // Drop existing foreign keys and columns
            $table->dropForeign(['shipment_id']);
            $table->dropForeign(['truck_id']);
            $table->dropColumn(['shipment_id', 'truck_id']);

            // Add new columns for vehicle and trailer (UUID to match vehicles/trailers tables)
            $table->uuid('vehicle_id')->nullable()->after('driver_id');
            $table->uuid('trailer_id')->nullable()->after('vehicle_id');

            // Add assignment code
            $table->string('assignment_code', 50)->unique()->after('id');

            // Add is_active flag
            $table->boolean('is_active')->default(true)->after('status_id');

            // Add audit fields
            $table->unsignedBigInteger('created_by')->nullable()->after('ended_at');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            // Add notes
            $table->text('notes')->nullable()->after('updated_by');

            // Add soft deletes
            $table->softDeletes();

            // Add new foreign keys
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('restrict');
            $table->foreign('trailer_id')->references('id')->on('trailers')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Add indexes
            $table->index('vehicle_id');
            $table->index('trailer_id');
            $table->index('is_active');
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('assignment_code');
        });

        // Drop old partial unique indexes
        DB::statement('DROP INDEX IF EXISTS assignments_driver_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_shipment_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_truck_id_active_unique');

        // Create new partial unique indexes for one active assignment per asset
        DB::statement('CREATE UNIQUE INDEX assignments_driver_id_active_unique ON assignments (driver_id) WHERE is_active = true AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_vehicle_id_active_unique ON assignments (vehicle_id) WHERE is_active = true AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_trailer_id_active_unique ON assignments (trailer_id) WHERE is_active = true AND deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Drop new foreign keys and columns
            $table->dropForeign(['vehicle_id']);
            $table->dropForeign(['trailer_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['vehicle_id']);
            $table->dropIndex(['trailer_id']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);
            $table->dropIndex(['assignment_code']);
            $table->dropColumn(['vehicle_id', 'trailer_id', 'assignment_code', 'is_active', 'created_by', 'updated_by', 'notes']);
            $table->dropSoftDeletes();

            // Restore old columns
            $table->foreignId('shipment_id')->nullable()->after('id')->constrained('shipments')->restrictOnDelete();
            $table->foreignId('truck_id')->nullable()->after('driver_id')->constrained('trucks')->restrictOnDelete();
        });

        // Drop new partial unique indexes
        DB::statement('DROP INDEX IF EXISTS assignments_driver_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_vehicle_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS assignments_trailer_id_active_unique');

        // Restore old partial unique indexes
        DB::statement('CREATE UNIQUE INDEX assignments_driver_id_active_unique ON assignments (driver_id) WHERE ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_shipment_id_active_unique ON assignments (shipment_id) WHERE ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX assignments_truck_id_active_unique ON assignments (truck_id) WHERE ended_at IS NULL');
    }
};
