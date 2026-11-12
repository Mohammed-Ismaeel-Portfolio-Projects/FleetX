<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_weight_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_policy_id')->unique()->constrained('pricing_policies')->cascadeOnDelete();
            $table->decimal('threshold_tons', 10, 3);
            $table->decimal('charge_per_ton', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_weight_configs');
    }
};
