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
        // 1. Vehicles table
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'company_id')) {
                $table->uuid('company_id')->nullable()->after('vehicle_type_id');
                $table->string('ownership_type', 32)->default('internal')->after('company_id');
                
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();

                $table->index('company_id');
                $table->index('ownership_type');
                $table->index(['ownership_type', 'company_id']);
            }
        });

        // 2. Drivers table
        Schema::table('drivers', function (Blueprint $table) {
            if (! Schema::hasColumn('drivers', 'company_id')) {
                $table->uuid('company_id')->nullable()->after('user_id');
                $table->string('ownership_type', 32)->default('internal')->after('company_id');

                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();

                $table->index('company_id');
                $table->index('ownership_type');
                $table->index(['ownership_type', 'company_id']);
            }
        });

        // 3. Trailers table
        Schema::table('trailers', function (Blueprint $table) {
            if (! Schema::hasColumn('trailers', 'company_id')) {
                $table->uuid('company_id')->nullable()->after('trailer_type');
                $table->string('ownership_type', 32)->default('internal')->after('company_id');

                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();

                $table->index('company_id');
                $table->index('ownership_type');
                $table->index(['ownership_type', 'company_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trailers', function (Blueprint $table) {
            if (Schema::hasColumn('trailers', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropIndex(['ownership_type']);
                $table->dropIndex(['ownership_type', 'company_id']);
                $table->dropColumn(['company_id', 'ownership_type']);
            }
        });

        Schema::table('drivers', function (Blueprint $table) {
            if (Schema::hasColumn('drivers', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropIndex(['ownership_type']);
                $table->dropIndex(['ownership_type', 'company_id']);
                $table->dropColumn(['company_id', 'ownership_type']);
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropIndex(['ownership_type']);
                $table->dropIndex(['ownership_type', 'company_id']);
                $table->dropColumn(['company_id', 'ownership_type']);
            }
        });
    }
};
