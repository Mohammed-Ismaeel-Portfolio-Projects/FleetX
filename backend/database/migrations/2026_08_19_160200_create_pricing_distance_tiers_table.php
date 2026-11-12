<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_distance_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_policy_id')->constrained('pricing_policies')->cascadeOnDelete();
            $table->decimal('min_distance_km', 10, 2);
            $table->decimal('max_distance_km', 10, 2)->nullable(); // null = unlimited
            $table->decimal('rate_per_km', 8, 4);
            $table->timestamps();

            $table->index('pricing_policy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_distance_tiers');
    }
};
