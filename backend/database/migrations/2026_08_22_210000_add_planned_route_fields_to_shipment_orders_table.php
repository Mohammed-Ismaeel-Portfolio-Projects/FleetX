<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('planned_distance_meters')->nullable()->after('delivery_longitude');
            $table->unsignedInteger('planned_duration_seconds')->nullable()->after('planned_distance_meters');
            $table->json('planned_route_geometry')->nullable()->after('planned_duration_seconds');
            $table->decimal('planned_route_origin_latitude', 10, 7)->nullable()->after('planned_route_geometry');
            $table->decimal('planned_route_origin_longitude', 10, 7)->nullable()->after('planned_route_origin_latitude');
            $table->decimal('planned_route_destination_latitude', 10, 7)->nullable()->after('planned_route_origin_longitude');
            $table->decimal('planned_route_destination_longitude', 10, 7)->nullable()->after('planned_route_destination_latitude');
            $table->string('planned_route_status', 30)->nullable()->after('planned_route_destination_longitude');
            $table->timestamp('planned_route_calculated_at')->nullable()->after('planned_route_status');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->dropColumn([
                'planned_distance_meters',
                'planned_duration_seconds',
                'planned_route_geometry',
                'planned_route_origin_latitude',
                'planned_route_origin_longitude',
                'planned_route_destination_latitude',
                'planned_route_destination_longitude',
                'planned_route_status',
                'planned_route_calculated_at',
            ]);
        });
    }
};
