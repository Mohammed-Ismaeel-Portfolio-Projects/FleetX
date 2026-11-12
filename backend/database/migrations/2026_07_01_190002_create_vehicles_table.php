<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('operational_code', 50)->unique()->comment('FleetX operational vehicle identifier');
            $table->string('fleet_number', 50)->comment('Internal fleet number used by dispatch and maintenance');
            $table->string('plate_number', 50)->unique()->comment('Legal registration plate number');
            $table->string('vin', 50)->nullable()->unique()->comment('Vehicle identification number');

            $table->string('manufacturer', 100);
            $table->string('model', 100);
            $table->unsignedSmallInteger('production_year')->nullable();
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types')->restrictOnDelete();
            $table->foreignId('fuel_type_id')->constrained('fuel_types')->restrictOnDelete();
            $table->string('transmission', 30)->nullable();
            $table->unsignedTinyInteger('axle_count')->nullable();
            $table->unsignedInteger('payload_capacity_kg')->nullable();
            $table->unsignedInteger('gross_weight_kg')->nullable();
            $table->unsignedInteger('fuel_tank_capacity_l')->nullable();
            $table->unsignedBigInteger('current_odometer')->nullable();

            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('gps_device_identifier', 100)->nullable();

            $table->date('insurance_expiry_date')->nullable();
            $table->date('registration_expiry_date')->nullable();
            $table->date('inspection_expiry_date')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('fleet_number');
            $table->index('vehicle_type_id');
            $table->index('fuel_type_id');
            $table->index('status_id');
            $table->index('is_active');
            $table->index(['status_id', 'is_active']);
            $table->index('gps_device_identifier');
            $table->index('insurance_expiry_date');
            $table->index('registration_expiry_date');
            $table->index('inspection_expiry_date');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
