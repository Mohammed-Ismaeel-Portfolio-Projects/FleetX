<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messaging SSOT: conversations (shipment-scoped threads).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 40);
            $table->uuid('shipment_id')->nullable();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments')->nullOnDelete();
            $table->foreign('assignment_id')->references('id')->on('assignments')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('type');
            $table->index('status');
            $table->index('shipment_id');
            // One open thread per type per shipment (duplicate prevention).
            $table->unique(['type', 'shipment_id'], 'conversations_type_shipment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
