<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trailers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('trailer_code', 50)->unique()->comment('FleetX trailer identifier');
            $table->string('fleet_number', 50)->comment('Internal fleet number used by dispatch and maintenance');
            $table->string('plate_number', 50)->unique()->comment('Legal registration plate number');

            $table->string('trailer_type', 50)->comment('Type of trailer (e.g., Dry Van, Refrigerated)');
            $table->string('manufacturer', 100)->comment('Trailer manufacturer');
            $table->string('model', 100)->nullable()->comment('Trailer model');
            $table->unsignedSmallInteger('production_year')->nullable()->comment('Year of manufacture');

            $table->unsignedTinyInteger('axle_count')->nullable()->comment('Number of axles');
            $table->unsignedInteger('payload_capacity_kg')->nullable()->comment('Maximum payload capacity in kilograms');
            $table->decimal('length_m', 8, 2)->nullable()->comment('Trailer length in meters');
            $table->decimal('width_m', 8, 2)->nullable()->comment('Trailer width in meters');
            $table->decimal('height_m', 8, 2)->nullable()->comment('Trailer height in meters');
            $table->decimal('cargo_volume_m3', 10, 2)->nullable()->comment('Cargo volume in cubic meters');

            $table->unsignedBigInteger('current_odometer')->nullable()->comment('Current odometer reading in kilometers');

            $table->string('gps_device_identifier', 100)->nullable()->comment('GPS device identifier for tracking');

            $table->date('insurance_expiry_date')->nullable()->comment('Insurance policy expiry date');
            $table->date('inspection_expiry_date')->nullable()->comment('Next inspection expiry date');
            $table->date('registration_expiry_date')->nullable()->comment('Registration expiry date');

            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete()->comment('Status reference');
            $table->boolean('is_active')->default(true)->comment('Quick active/inactive flag');
            $table->text('notes')->nullable()->comment('Internal notes');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User who created the record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('User who last updated the record');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('fleet_number');
            $table->index('trailer_type');
            $table->index('manufacturer');
            $table->index('status_id');
            $table->index('is_active');
            $table->index(['status_id', 'is_active']);
            $table->index('gps_device_identifier');
            $table->index('insurance_expiry_date');
            $table->index('inspection_expiry_date');
            $table->index('registration_expiry_date');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trailers');
    }
};
