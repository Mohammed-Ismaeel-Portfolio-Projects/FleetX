<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_policy_id')->constrained('pricing_policies')->cascadeOnDelete();
            $table->string('customer_type');
            $table->string('discount_type'); // percentage, flat
            $table->decimal('amount', 12, 4);
            $table->decimal('max_discount', 12, 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['pricing_policy_id', 'customer_type']);
            $table->index('pricing_policy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_discounts');
    }
};
