<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the tracking_logs table and recreate with correct UUID type
        Schema::dropIfExists('tracking_logs');

        Schema::create('tracking_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('shipment_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('recorded_at');

            // Add foreign key constraint
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->onDelete('cascade');

            $table->index(['shipment_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracking_logs', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropColumn('shipment_id');
        });

        Schema::table('tracking_logs', function (Blueprint $table) {
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
        });
    }
};
