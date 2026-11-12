<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client User ↔ Company membership (minimum consistent pattern).
 * One client user belongs to at most one Company; many users may share a Company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('company_id')->nullable()->after('role_id');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->nullOnDelete();
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
