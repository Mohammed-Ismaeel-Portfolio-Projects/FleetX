<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_operational_actuals', function (Blueprint $table) {
            $table->id();
            $table->uuid('shipment_id')->unique();
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->cascadeOnDelete();
            $table->decimal('actual_distance_km', 12, 3)->nullable();
            $table->decimal('actual_cargo_weight', 10, 2)->nullable();
            $table->unsignedInteger('actual_loading_wait_minutes')->nullable();
            $table->unsignedInteger('actual_unloading_wait_minutes')->nullable();
            $table->unsignedInteger('actual_traffic_delay_minutes')->nullable();
            $table->unsignedInteger('actual_customer_delay_minutes')->nullable();
            $table->boolean('actual_border_crossing')->nullable();
            $table->boolean('actual_hazmat')->nullable();
            $table->json('actual_service_ids')->nullable();
            $table->string('actual_trailer_type', 50)->nullable();
            $table->string('capture_source', 50);
            $table->timestamp('captured_at');
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_operational_actuals');
    }
};
