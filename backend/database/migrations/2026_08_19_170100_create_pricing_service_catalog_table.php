<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_service_catalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_policy_id')
                ->constrained('pricing_policies')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['pricing_policy_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_service_catalog');
    }
};
