<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_policy_id')->constrained('pricing_policies')->cascadeOnDelete();
            $table->string('code'); // border, insurance, hazmat, heavy_reefer
            $table->string('name');
            $table->string('fee_type'); // flat, percentage
            $table->decimal('amount', 12, 4);
            $table->string('condition_field')->nullable();
            $table->string('condition_value')->nullable();
            $table->decimal('max_amount', 12, 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['pricing_policy_id', 'code']);
            $table->index('pricing_policy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_fees');
    }
};
