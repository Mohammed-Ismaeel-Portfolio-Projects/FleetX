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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->string('file_url')->nullable();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('filters')->nullable();
            $table->timestamps();

            $table->index('generated_by');
            $table->index('status_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
