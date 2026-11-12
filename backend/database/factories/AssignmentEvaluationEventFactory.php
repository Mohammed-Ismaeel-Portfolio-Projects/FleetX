<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\AssignmentEvaluation;
use App\Models\AssignmentEvaluationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssignmentEvaluationEvent>
 */
class AssignmentEvaluationEventFactory extends Factory
{
    protected $model = AssignmentEvaluationEvent::class;

    public function definition(): array
    {
        $evaluation = AssignmentEvaluation::factory()->create();
        $code = DriverEvaluationEventCode::SPEEDING;

        return [
            'assignment_evaluation_id' => $evaluation->id,
            'assignment_id' => $evaluation->assignment_id,
            'shipment_id' => $evaluation->shipment_id,
            'driver_id' => $evaluation->driver_id,
            'vehicle_id' => $evaluation->vehicle_id,
            'event_code' => $code,
            'category' => $code->category(),
            'severity' => $code->defaultSeverity(),
            'occurred_at' => now(),
            'window_start_at' => now()->subSeconds(30),
            'window_end_at' => now(),
            'evidence_source' => DriverEvaluationEvidenceSource::LIVE,
            'detector_code' => 'eval.foundation',
            'detector_version' => '1.0.0',
            'idempotency_key' => 'factory:'.Str::uuid()->toString(),
            'evidence_tracking_log_ids' => [1, 2, 3],
            'evidence_metadata' => ['note' => 'factory'],
        ];
    }

    public function forEvaluation(AssignmentEvaluation $evaluation): static
    {
        return $this->state(fn () => [
            'assignment_evaluation_id' => $evaluation->id,
            'assignment_id' => $evaluation->assignment_id,
            'shipment_id' => $evaluation->shipment_id,
            'driver_id' => $evaluation->driver_id,
            'vehicle_id' => $evaluation->vehicle_id,
        ]);
    }
}
