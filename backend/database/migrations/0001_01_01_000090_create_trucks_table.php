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
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number')->unique();
            $table->decimal('capacity', 10, 2);
            $table->string('type');
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->string('fuel_type');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
