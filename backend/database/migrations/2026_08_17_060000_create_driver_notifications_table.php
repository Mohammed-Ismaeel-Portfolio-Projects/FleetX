<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D8 — Driver-scoped operational notifications (separate from Client notifications).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('title');
            $table->text('body');
            $table->string('severity', 16)->default('info');
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->uuid('shipment_id')->nullable();
            $table->string('action_kind', 32)->nullable();
            $table->string('action_target', 64)->nullable();
            $table->string('dedupe_key', 128)->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'read_at']);
            $table->index(['driver_id', 'created_at']);
            $table->index('code');
            $table->foreign('assignment_id')->references('id')->on('assignments')->nullOnDelete();
            $table->foreign('shipment_id')->references('id')->on('shipments')->nullOnDelete();
            $table->unique(['driver_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_notifications');
    }
};
