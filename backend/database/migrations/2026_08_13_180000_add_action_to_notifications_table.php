<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C2.9-C3.8 — Client-safe notification destination persistence.
 *
 * Existing inbox rows remain valid with null action (inbox fallback).
 * Does not store assignment, driver, vehicle, or Engine identifiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('action_kind')->nullable()->after('is_read');
            $table->uuid('action_id')->nullable()->after('action_kind');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['action_kind', 'action_id']);
        });
    }
};
