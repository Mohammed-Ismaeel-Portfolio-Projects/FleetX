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
        Schema::create('companies', function (Blueprint $table) {
            // Primary Key
            $table->uuid('id')->primary();
            
            // Company Identification
            $table->string('company_name', 255)->comment('Display name for UI and communications');
            $table->string('legal_name', 255)->comment('Official legal name for contracts and invoices');
            $table->string('company_code', 50)->unique()->comment('Unique human-readable identifier');
            
            // Tax and Registration
            $table->string('tax_number', 100)->nullable()->comment('Tax identification number');
            $table->string('vat_number', 100)->nullable()->comment('VAT registration number');
            $table->string('business_registration_number', 100)->nullable()->comment('Official business registration number');
            
            // Company Classification (Foreign keys for future lookup tables)
            $table->unsignedBigInteger('industry_id')->nullable()->comment('Industry classification reference');
            $table->unsignedBigInteger('company_size_id')->nullable()->comment('Company size reference');
            
            // Contact Information
            $table->string('primary_email', 255)->comment('Main contact email');
            $table->string('primary_phone', 50)->nullable()->comment('Main contact phone');
            $table->string('website', 255)->nullable()->comment('Company website');
            
            // Address Information (Headquarters address - supports multi-branch future)
            $table->string('country', 2)->comment('ISO 3166-1 alpha-2 country code');
            $table->string('state_province', 100)->nullable()->comment('State or province name');
            $table->string('district', 100)->nullable()->comment('District or region');
            $table->string('city', 100)->comment('City name');
            $table->string('building_number', 50)->nullable()->comment('Building number');
            $table->string('address_line_1', 255)->comment('Primary street address');
            $table->string('address_line_2', 255)->nullable()->comment('Secondary address');
            $table->string('postal_code', 20)->comment('Postal/ZIP code');
            
            // Geographic Data
            $table->decimal('latitude', 10, 8)->nullable()->comment('Geographic latitude');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Geographic longitude');
            
            // Localization
            $table->string('timezone', 50)->nullable()->comment('Company timezone');
            $table->string('currency_code', 3)->comment('Default currency ISO 4217');
            $table->string('language_code', 5)->nullable()->comment('Preferred language ISO 639-1');
            
            // Status and Metadata
            $table->unsignedBigInteger('status_id')->default('1')->comment('Status reference to statuses table');
            $table->boolean('is_active')->default(true)->comment('Quick active/inactive flag for queries');
            $table->text('notes')->nullable()->comment('Internal notes');
            $table->string('logo_path', 255)->nullable()->comment('File path to company logo');
            
            // Audit Fields (following FleetX Master Architecture)
            $table->uuid('created_by')->nullable()->comment('User who created the record');
            $table->uuid('updated_by')->nullable()->comment('User who last updated the record');
            
            // Timestamps
            $table->timestamps();
            
            // Soft Deletes
            $table->softDeletes();
            
            // Foreign Keys (nullable to allow lookup tables to be created later)
            $table->foreign('status_id')->references('id')->on('statuses')->onDelete('restrict');
            // industry_id and company_size_id foreign keys will be added when lookup tables are created
            
            // Indexes - Optimized for common query patterns
            $table->index('status_id');
            $table->index('is_active');
            $table->index('country');
            $table->index('primary_email');
            $table->index('company_code');
            $table->index(['country', 'city']);
            $table->index(['is_active', 'country']);
            $table->index(['status_id', 'is_active']);
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
