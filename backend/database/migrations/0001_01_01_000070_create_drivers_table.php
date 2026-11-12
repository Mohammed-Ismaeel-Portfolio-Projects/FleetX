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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('nationality', 100);
            $table->string('license_number')->unique();
            $table->string('phone');
            $table->string('email')->unique();
            $table->text('address')->nullable();
            $table->date('employment_date');
            $table->string('license_class', 50);
            $table->date('license_expiry_date');
            $table->date('medical_certificate_expiry')->nullable();
            $table->unsignedSmallInteger('years_of_experience')->default(0);
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_number');
            $table->index('license_number');
            $table->index('email');
            $table->index('status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
