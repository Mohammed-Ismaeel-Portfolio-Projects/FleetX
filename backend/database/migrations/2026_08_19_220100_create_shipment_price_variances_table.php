<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_price_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_final_price_breakdown_id')
                ->constrained('shipment_final_price_breakdowns')
                ->cascadeOnDelete();
            $table->string('factor_code', 50);
            $table->decimal('baseline_value', 12, 4)->nullable();
            $table->decimal('actual_value', 12, 4)->nullable();
            $table->decimal('delta_value', 12, 4)->nullable();
            $table->string('delta_unit', 20)->nullable();
            $table->decimal('threshold_value', 12, 4)->nullable();
            $table->string('threshold_type', 20)->nullable();
            $table->string('threshold_scope', 30)->nullable();
            $table->boolean('material');
            $table->boolean('billable');
            $table->decimal('price_impact', 12, 2)->default(0);
            $table->string('reason_code', 50)->nullable();
            $table->text('customer_message')->nullable();
            $table->text('internal_message')->nullable();
            $table->string('data_source', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['shipment_final_price_breakdown_id', 'factor_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_price_variances');
    }
};
