<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D6 — server receive time for tracking logs (additive; Ops/Client ignore).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_logs', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_logs', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};
