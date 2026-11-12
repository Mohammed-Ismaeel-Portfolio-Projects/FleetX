<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft, active, scheduled, expired, archived
            $table->char('currency', 3)->default('USD');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0.0800); // e.g. 0.0800 = 8%
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('minimum_fare', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('effective_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_policies');
    }
};
