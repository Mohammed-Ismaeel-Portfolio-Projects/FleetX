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
        // Drop dependent foreign key constraints
        Schema::table('delivery_proofs', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
        });
        Schema::table('routes', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
        });
        Schema::table('tracking_logs', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
        });
        Schema::table('issues', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
        });

        // Drop existing table and recreate with new schema
        Schema::dropIfExists('shipments');

        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('shipment_number', 50)->unique();
            $table->uuid('customer_company_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            
            // Cargo details
            $table->string('cargo_type', 100)->nullable();
            $table->text('cargo_description')->nullable();
            $table->decimal('cargo_weight', 10, 2)->nullable();
            $table->decimal('cargo_volume', 10, 2)->nullable();
            $table->integer('package_count')->nullable();
            $table->boolean('hazardous_cargo')->default(false);
            $table->boolean('temperature_controlled')->default(false);
            
            // Pickup details
            $table->string('pickup_company', 200)->nullable();
            $table->string('pickup_contact', 100)->nullable();
            $table->string('pickup_phone', 50)->nullable();
            $table->text('pickup_address')->nullable();
            $table->string('pickup_city', 100)->nullable();
            $table->string('pickup_country', 100)->nullable();
            
            // Delivery details
            $table->string('delivery_company', 200)->nullable();
            $table->string('delivery_contact', 100)->nullable();
            $table->string('delivery_phone', 50)->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_country', 100)->nullable();
            
            // Dates
            $table->date('planned_pickup_date')->nullable();
            $table->date('planned_delivery_date')->nullable();
            $table->date('actual_pickup_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            
            // Additional fields
            $table->text('special_instructions')->nullable();
            $table->text('notes')->nullable();
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Add foreign keys
            $table->foreign('customer_company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Add indexes
            $table->index('customer_company_id');
            $table->index('shipment_number');
            $table->index('priority');
            $table->index('pickup_country');
            $table->index('delivery_country');
            $table->index('planned_pickup_date');
            $table->index('planned_delivery_date');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Drop new foreign keys and columns
            $table->dropForeign(['customer_company_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['customer_company_id']);
            $table->dropIndex(['shipment_number']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['pickup_country']);
            $table->dropIndex(['delivery_country']);
            $table->dropIndex(['planned_pickup_date']);
            $table->dropIndex(['planned_delivery_date']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);
            
            $table->dropColumn([
                'shipment_number',
                'customer_company_id',
                'reference_number',
                'priority',
                'cargo_type',
                'cargo_description',
                'cargo_weight',
                'cargo_volume',
                'package_count',
                'hazardous_cargo',
                'temperature_controlled',
                'pickup_company',
                'pickup_contact',
                'pickup_phone',
                'pickup_address',
                'pickup_city',
                'pickup_country',
                'delivery_company',
                'delivery_contact',
                'delivery_phone',
                'delivery_address',
                'delivery_city',
                'delivery_country',
                'planned_pickup_date',
                'planned_delivery_date',
                'actual_pickup_date',
                'actual_delivery_date',
                'special_instructions',
                'notes',
                'created_by',
                'updated_by',
            ]);

            // Restore old columns
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->decimal('total_weight', 10, 2);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('pickup_time')->nullable();
            $table->timestamp('delivery_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
        });
    }
};
