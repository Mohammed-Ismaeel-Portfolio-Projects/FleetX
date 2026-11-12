<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D4 — Driver execution phase on assignments (nullable; Driver-scoped).
 * Ops behavior unchanged; null means no Driver execution session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('execution_phase', 64)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('execution_phase');
        });
    }
};
