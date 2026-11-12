<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_final_price_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->uuid('shipment_id')->unique();
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->cascadeOnDelete();
            $table->uuid('shipment_order_id');
            $table->foreign('shipment_order_id')
                ->references('id')
                ->on('shipment_orders')
                ->restrictOnDelete();
            $table->foreignId('submission_price_breakdown_id')
                ->constrained('shipment_order_price_breakdowns')
                ->restrictOnDelete();
            $table->foreignId('pricing_policy_id')
                ->constrained('pricing_policies')
                ->restrictOnDelete();
            $table->foreignId('operational_actuals_id')
                ->nullable()
                ->constrained('shipment_operational_actuals')
                ->restrictOnDelete();
            $table->json('breakdown_json');
            $table->decimal('submission_total', 12, 2);
            $table->decimal('final_total', 12, 2);
            $table->decimal('price_delta', 12, 2);
            $table->decimal('price_delta_percentage', 8, 4)->nullable();
            $table->string('change_direction', 20);
            $table->boolean('material_change');
            $table->char('currency', 3);
            $table->timestamp('calculated_at');
            $table->foreignId('calculated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('calculation_trigger', 30);
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['shipment_order_id']);
            $table->index(['status']);
            $table->index(['calculation_trigger']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_final_price_breakdowns');
    }
};
