<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D12 — Driver device push tokens (Expo). Separate table from the inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('platform', 16);
            $table->string('push_token', 255);
            $table->string('device_id', 128)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->unique('push_token');
            $table->index(['driver_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_devices');
    }
};
