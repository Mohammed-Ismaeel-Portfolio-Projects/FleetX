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
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'executing_company_id')) {
                $table->uuid('executing_company_id')->nullable()->after('customer_company_id');
                $table->string('collaboration_type', 32)->default('internal')->after('executing_company_id');

                $table->foreign('executing_company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();

                $table->index('executing_company_id');
                $table->index('collaboration_type');
                $table->index(['collaboration_type', 'executing_company_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'executing_company_id')) {
                $table->dropForeign(['executing_company_id']);
                $table->dropIndex(['executing_company_id']);
                $table->dropIndex(['collaboration_type']);
                $table->dropIndex(['collaboration_type', 'executing_company_id']);
                $table->dropColumn(['executing_company_id', 'collaboration_type']);
            }
        });
    }
};
