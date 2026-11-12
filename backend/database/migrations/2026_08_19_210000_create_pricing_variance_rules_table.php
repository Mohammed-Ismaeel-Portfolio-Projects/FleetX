<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_variance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_policy_id')
                ->constrained('pricing_policies')
                ->cascadeOnDelete();
            $table->string('factor_code', 50);
            $table->string('threshold_type', 20);
            $table->decimal('threshold_value', 12, 4);
            $table->boolean('applies_to_increase')->default(true);
            $table->boolean('applies_to_decrease')->default(false);
            $table->boolean('enabled')->default(true);
            $table->text('customer_message_template')->nullable();
            $table->text('internal_message_template')->nullable();
            $table->timestamps();

            $table->unique(['pricing_policy_id', 'factor_code']);
            $table->index(['pricing_policy_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_variance_rules');
    }
};
