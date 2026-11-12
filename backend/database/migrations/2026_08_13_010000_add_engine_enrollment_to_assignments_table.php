<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C2.9-C2.8 Step 3 — Engine trip enrollment on assignments.
 *
 * Null / non-eligible status = not simulation-driven.
 * Active assignments are no longer implicitly simulated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('engine_enrollment_status', 32)->nullable()->after('is_active');
            $table->timestamp('engine_enrolled_at')->nullable()->after('engine_enrollment_status');

            $table->index('engine_enrollment_status');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex(['engine_enrollment_status']);
            $table->dropColumn(['engine_enrollment_status', 'engine_enrolled_at']);
        });
    }
};
