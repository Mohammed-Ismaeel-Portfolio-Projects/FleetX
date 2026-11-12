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
        Schema::table('delivery_proofs', function (Blueprint $table) {
            $table->foreignId('assignment_id')->nullable()->after('shipment_id')->constrained('assignments')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->after('assignment_id')->constrained('drivers')->nullOnDelete();
            $table->string('receiver_name')->nullable()->after('driver_id');
            $table->string('receiver_role')->nullable()->after('receiver_name');
            $table->string('condition_status')->default('good')->after('receiver_role');
            $table->decimal('latitude', 10, 7)->nullable()->after('condition_status');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->text('delivery_notes')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_proofs', function (Blueprint $table) {
            $table->dropForeign(['assignment_id']);
            $table->dropForeign(['driver_id']);
            $table->dropColumn([
                'assignment_id',
                'driver_id',
                'receiver_name',
                'receiver_role',
                'condition_status',
                'latitude',
                'longitude',
                'delivery_notes',
            ]);
        });
    }
};
