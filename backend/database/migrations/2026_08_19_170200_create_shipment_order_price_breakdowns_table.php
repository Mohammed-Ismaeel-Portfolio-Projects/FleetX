<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_order_price_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->uuid('shipment_order_id')->unique();
            $table->foreign('shipment_order_id')
                ->references('id')
                ->on('shipment_orders')
                ->cascadeOnDelete();
            $table->foreignId('pricing_policy_id')
                ->constrained('pricing_policies')
                ->restrictOnDelete();
            $table->json('breakdown_json');
            $table->decimal('final_total', 12, 2);
            $table->char('currency', 3);
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_order_price_breakdowns');
    }
};
