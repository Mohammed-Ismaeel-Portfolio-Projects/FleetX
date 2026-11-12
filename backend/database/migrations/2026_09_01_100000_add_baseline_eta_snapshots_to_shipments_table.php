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
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedInteger('baseline_eta_seconds')->nullable()->after('status_id');
            $table->unsignedBigInteger('baseline_distance_meters')->nullable()->after('baseline_eta_seconds');
            $table->string('baseline_routing_provider', 50)->nullable()->after('baseline_distance_meters');
            $table->timestamp('baseline_calculated_at')->nullable()->after('baseline_routing_provider');
            $table->timestamp('actual_pickup_at')->nullable()->after('actual_pickup_date');
            $table->timestamp('actual_delivery_at')->nullable()->after('actual_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'baseline_eta_seconds',
                'baseline_distance_meters',
                'baseline_routing_provider',
                'baseline_calculated_at',
                'actual_pickup_at',
                'actual_delivery_at',
            ]);
        });
    }
};
