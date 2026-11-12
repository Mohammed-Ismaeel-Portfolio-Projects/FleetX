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
        Schema::create('shipment_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 50)->unique();
            $table->uuid('customer_company_id');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->unsignedInteger('submission_revision')->default(0);
            $table->json('payload_json')->nullable();

            $table->string('reference_number', 100)->nullable();
            $table->string('priority', 20)->nullable();

            // Cargo (inline snapshot — A2)
            $table->string('cargo_type', 100)->nullable();
            $table->text('cargo_description')->nullable();
            $table->decimal('cargo_weight', 10, 2)->nullable();
            $table->decimal('cargo_volume', 10, 2)->nullable();
            $table->integer('package_count')->nullable();
            $table->boolean('hazardous_cargo')->default(false);
            $table->boolean('temperature_controlled')->default(false);

            // Pickup snapshot
            $table->string('pickup_company', 200)->nullable();
            $table->string('pickup_contact', 100)->nullable();
            $table->string('pickup_phone', 50)->nullable();
            $table->text('pickup_address')->nullable();
            $table->string('pickup_city', 100)->nullable();
            $table->string('pickup_country', 100)->nullable();

            // Delivery snapshot
            $table->string('delivery_company', 200)->nullable();
            $table->string('delivery_contact', 100)->nullable();
            $table->string('delivery_phone', 50)->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_country', 100)->nullable();

            // Scheduling
            $table->date('requested_pickup_date')->nullable();
            $table->date('requested_delivery_date')->nullable();

            $table->text('special_instructions')->nullable();
            $table->text('client_notes')->nullable();
            $table->text('dispatcher_notes')->nullable();

            // Conversion traceability (one order → one shipment at DB level)
            $table->uuid('converted_shipment_id')->nullable()->unique();

            $table->timestamps();

            $table->foreign('customer_company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('converted_shipment_id')->references('id')->on('shipments')->restrictOnDelete();

            $table->index('customer_company_id');
            $table->index('status_id');
            $table->index('submitted_by_user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_orders');
    }
};
