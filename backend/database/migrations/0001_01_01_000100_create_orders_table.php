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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pickup_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('delivery_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('pricing_rule_id')->constrained('pricing_rules')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->decimal('estimated_cost', 12, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('status_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
