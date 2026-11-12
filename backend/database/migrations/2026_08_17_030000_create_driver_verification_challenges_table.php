<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D5 — Driver verification challenges (opaque QR references).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_verification_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->unsignedBigInteger('assignment_id');
            $table->string('event', 32);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedBigInteger('consumed_by_user_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments')->cascadeOnDelete();
            $table->foreign('assignment_id')->references('id')->on('assignments')->cascadeOnDelete();
            $table->foreign('consumed_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['shipment_id', 'event']);
            $table->index(['assignment_id', 'event']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_verification_challenges');
    }
};
