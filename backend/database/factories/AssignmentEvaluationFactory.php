<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentEvaluation>
 */
class AssignmentEvaluationFactory extends Factory
{
    protected $model = AssignmentEvaluation::class;

    public function definition(): array
    {
        $assignment = Assignment::factory()->create();

        return [
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $assignment->driver_id,
            'vehicle_id' => $assignment->vehicle_id,
            'evaluation_mode' => AssignmentEvaluationMode::LIVE,
            'status' => AssignmentEvaluationStatus::PENDING,
            'evidence_quality' => null,
            'revision' => 1,
            'supersedes_evaluation_id' => null,
            'started_at' => now(),
            'completed_at' => null,
            'finalized_at' => null,
            'metadata' => null,
        ];
    }

    public function forAssignment(Assignment $assignment): static
    {
        return $this->state(fn () => [
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $assignment->driver_id,
            'vehicle_id' => $assignment->vehicle_id,
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn () => [
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'evidence_quality' => \App\Enums\AssignmentEvaluationEvidenceQuality::SUFFICIENT,
            'completed_at' => now(),
            'finalized_at' => now(),
        ]);
    }

    public function incomplete(): static
    {
        return $this->state(fn () => [
            'status' => AssignmentEvaluationStatus::INCOMPLETE,
            'evidence_quality' => \App\Enums\AssignmentEvaluationEvidenceQuality::INSUFFICIENT,
            'completed_at' => now(),
            'finalized_at' => now(),
            'metadata' => ['incomplete_reason' => 'No live telemetry samples.'],
        ]);
    }
}
