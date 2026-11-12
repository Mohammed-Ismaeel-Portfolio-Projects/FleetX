<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_base_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_policy_id')->constrained('pricing_policies')->cascadeOnDelete();
            $table->string('cargo_type');
            $table->string('trailer_type')->nullable(); // null = cargo-only fallback
            $table->decimal('rate_per_km', 8, 4);
            $table->timestamps();

            $table->unique(['pricing_policy_id', 'cargo_type', 'trailer_type'], 'pricing_base_rates_policy_cargo_trailer_unique');
            $table->index('pricing_policy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_base_rates');
    }
};
