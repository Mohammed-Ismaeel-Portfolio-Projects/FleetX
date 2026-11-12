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
        Schema::create('shipment_status_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('shipment_id');
            $table->foreignId('from_status_id')->nullable()->constrained('statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->constrained('statuses')->restrictOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete();

            $table->index('shipment_id');
            $table->index('to_status_id');
            $table->index('changed_by_user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_status_history');
    }
};
