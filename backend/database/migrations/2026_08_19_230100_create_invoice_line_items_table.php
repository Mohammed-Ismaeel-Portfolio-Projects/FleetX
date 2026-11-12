<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')
                ->constrained('monthly_invoices')
                ->cascadeOnDelete();
            $table->uuid('shipment_id')->unique();
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->restrictOnDelete();
            $table->foreignId('shipment_final_price_breakdown_id');
            $table->foreign('shipment_final_price_breakdown_id', 'invoice_line_items_final_price_fk')
                ->references('id')
                ->on('shipment_final_price_breakdowns')
                ->restrictOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->char('currency', 3);
            $table->timestamps();

            $table->index(['monthly_invoice_id']);
            $table->unique('shipment_final_price_breakdown_id', 'invoice_line_items_final_price_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
