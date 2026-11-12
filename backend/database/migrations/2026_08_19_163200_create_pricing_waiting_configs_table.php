<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_waiting_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_policy_id')->unique()->constrained('pricing_policies')->cascadeOnDelete();
            $table->unsignedInteger('loading_free_minutes');
            $table->unsignedInteger('unloading_free_minutes');
            $table->decimal('rate_per_15min', 12, 4);
            $table->decimal('max_charge', 12, 2);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_waiting_configs');
    }
};
