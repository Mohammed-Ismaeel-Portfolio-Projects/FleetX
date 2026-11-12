<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRIVER-EVAL-02 — Assignment evaluation domain foundation.
 *
 * Assignment remains the evaluation context (no Trip table).
 * No scores stored. Telemetry remains owned by tracking_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_evaluations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assignment_id')
                ->constrained('assignments')
                ->restrictOnDelete();

            // Historical identity snapshot at evaluation creation time.
            $table->uuid('shipment_id');
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->restrictOnDelete();

            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->restrictOnDelete();

            $table->uuid('vehicle_id');
            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->restrictOnDelete();

            $table->string('evaluation_mode', 20)->default('live');
            $table->string('status', 20)->default('pending');
            $table->string('evidence_quality', 20)->nullable();

            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('supersedes_evaluation_id')
                ->nullable()
                ->constrained('assignment_evaluations')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'revision'], 'assignment_evaluations_assignment_revision_unique');
            $table->index(['driver_id', 'status']);
            $table->index(['shipment_id']);
            $table->index(['status', 'finalized_at']);
            $table->index(['evaluation_mode']);
        });

        Schema::create('assignment_evaluation_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assignment_evaluation_id')
                ->constrained('assignment_evaluations')
                ->restrictOnDelete();

            // Denormalized for queryability / historical integrity without joins.
            $table->foreignId('assignment_id')
                ->constrained('assignments')
                ->restrictOnDelete();

            $table->uuid('shipment_id');
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->restrictOnDelete();

            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->restrictOnDelete();

            $table->uuid('vehicle_id');
            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->restrictOnDelete();

            $table->string('event_code', 64);
            $table->string('category', 32);
            $table->string('severity', 20);
            $table->timestamp('occurred_at');
            $table->timestamp('window_start_at')->nullable();
            $table->timestamp('window_end_at')->nullable();

            $table->string('evidence_source', 20);
            $table->string('detector_code', 64);
            $table->string('detector_version', 32);

            $table->string('idempotency_key', 191);
            $table->json('evidence_tracking_log_ids')->nullable();
            $table->json('evidence_metadata')->nullable();

            $table->timestamps();

            $table->unique('idempotency_key', 'assignment_evaluation_events_idempotency_unique');
            $table->index(['assignment_evaluation_id', 'event_code']);
            $table->index(['driver_id', 'occurred_at']);
            $table->index(['assignment_id', 'occurred_at']);
            $table->index(['shipment_id', 'occurred_at']);
            $table->index(['event_code', 'severity']);
            $table->index(['evidence_source']);
            $table->index(['detector_code', 'detector_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_evaluation_events');
        Schema::dropIfExists('assignment_evaluations');
    }
};
