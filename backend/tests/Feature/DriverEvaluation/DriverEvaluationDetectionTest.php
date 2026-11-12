<?php

declare(strict_types=1);

namespace Tests\Feature\DriverEvaluation;

use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\AssignmentEvaluationEvent;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\Vehicle;
use App\Services\DriverEvaluation\AssignmentEvaluationPersistenceService;
use App\Services\DriverEvaluation\Detection\DriverEvaluationDetectionService;
use App\Services\DriverEvaluation\Exceptions\DriverEvaluationException;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * DRIVER-EVAL-03 — evidence detection & persistence (no scoring).
 */
class DriverEvaluationDetectionTest extends TestCase
{
    use RefreshDatabase;

    private DriverEvaluationDetectionService $detection;

    private AssignmentEvaluationPersistenceService $persistence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->detection = app(DriverEvaluationDetectionService::class);
        $this->persistence = app(AssignmentEvaluationPersistenceService::class);

        config([
            'driver_safety.speed.enabled' => true,
            'driver_safety.speed.threshold_kmh' => 110,
            'driver_safety.speed.min_samples' => 3,
            'driver_safety.speed.min_duration_seconds' => 10,
            'driver_safety.motion.enabled' => true,
            'driver_safety.motion.min_samples' => 3,
            'driver_safety.motion.min_duration_seconds' => 10,
            'driver_safety.motion.min_interval_seconds' => 3,
            'driver_safety.motion.max_interval_seconds' => 15,
            'driver_safety.motion.harsh_brake_ms2.van' => 1.0,
            'driver_safety.motion.harsh_brake_ms2.light_truck' => 1.0,
            'driver_safety.motion.harsh_brake_ms2.heavy_truck' => 1.0,
            'driver_evaluation.harsh_acceleration.ms2.van' => 1.0,
            'driver_evaluation.harsh_acceleration.ms2.light_truck' => 1.0,
            'driver_evaluation.harsh_acceleration.ms2.heavy_truck' => 1.0,
            'driver_evaluation.idle.min_duration_seconds' => 30,
            'driver_evaluation.idle.min_samples' => 3,
            'driver_evaluation.idle.max_gap_seconds' => 60,
            'driver_evaluation.prolonged_stop.min_duration_seconds' => 30,
            'driver_evaluation.prolonged_stop.min_samples' => 3,
            'driver_evaluation.prolonged_stop.max_gap_seconds' => 60,
            'routing.deviation.enabled' => true,
            'routing.deviation.threshold_meters' => 100,
        ]);
    }

    public function test_live_telemetry_attributed_and_speeding_detected(): void
    {
        $assignment = $this->makeAssignment();
        $base = Carbon::parse('2026-08-01 12:00:00');
        $this->seedLiveSpeedSeries($assignment, $base, [120, 125, 130], intervalSeconds: 5);

        $result = $this->detection->processAssignment($assignment);

        $this->assertGreaterThan(0, $result->telemetrySampleCount);
        $event = AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->where('event_code', DriverEvaluationEventCode::SPEEDING->value)
            ->first();
        $this->assertNotNull($event);
        $this->assertSame(DriverEvaluationEvidenceSource::LIVE, $event->evidence_source);
        $this->assertSame($assignment->driver_id, $event->driver_id);
        $this->assertSame('eval.speeding', $event->detector_code);
        $this->assertSame('1.0.0', $event->detector_version);
        $this->assertNotEmpty($event->evidence_tracking_log_ids);
        $this->assertArrayHasKey('threshold_kmh', $event->evidence_metadata);
    }

    public function test_simulation_telemetry_rejected_from_live_evaluation(): void
    {
        $assignment = $this->makeAssignment();
        $base = Carbon::parse('2026-08-01 12:00:00');
        $this->seedSimSpeedSeries($assignment, $base, [150, 155, 160], intervalSeconds: 5);

        $result = $this->detection->processAssignment($assignment, AssignmentEvaluationMode::LIVE);

        $this->assertSame(0, $result->telemetrySampleCount);
        $this->assertSame(0, AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->where('event_code', DriverEvaluationEventCode::SPEEDING->value)
            ->count());
    }

    public function test_reassignment_does_not_misattribute_old_telemetry(): void
    {
        $shipment = Shipment::factory()->create();
        $driverA = Driver::factory()->create();
        $driverB = Driver::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();

        $a = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driverA->id,
            'vehicle_id' => $vehicleA->id,
            'assigned_at' => Carbon::parse('2026-08-01 08:00:00'),
            'ended_at' => Carbon::parse('2026-08-01 12:00:00'),
            'is_active' => false,
        ]);
        Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driverB->id,
            'vehicle_id' => $vehicleB->id,
            'assigned_at' => Carbon::parse('2026-08-01 12:00:01'),
            'ended_at' => null,
            'is_active' => true,
        ]);

        $this->seedLiveSpeedSeries($a, Carbon::parse('2026-08-01 10:00:00'), [120, 122, 125], 5);

        $result = $this->detection->processAssignment($a);
        $event = AssignmentEvaluationEvent::query()->where('assignment_id', $a->id)->first();

        $this->assertNotNull($event);
        $this->assertSame($driverA->id, $event->driver_id);
        $this->assertNotSame($driverB->id, $event->driver_id);
        $this->assertGreaterThan(0, $result->eventCount());
    }

    public function test_ambiguous_attribution_produces_no_event_for_overlapping_windows(): void
    {
        $shipment = Shipment::factory()->create();
        $t0 = Carbon::parse('2026-08-01 10:00:00');

        Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'assigned_at' => $t0->copy()->subHour(),
            'ended_at' => null,
            'is_active' => true,
        ]);
        Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'assigned_at' => $t0->copy()->subMinutes(30),
            'ended_at' => null,
            'is_active' => false,
        ]);

        // Telemetry exists but attribution is ambiguous → loader excludes it.
        TrackingLog::query()->create([
            'shipment_id' => $shipment->id,
            'latitude' => 52.52,
            'longitude' => 13.40,
            'speed' => 130,
            'heading' => 10,
            'accuracy' => 5,
            'recorded_at' => $t0,
            'received_at' => $t0->copy()->addSecond(),
        ]);

        $assignment = Assignment::query()->where('shipment_id', $shipment->id)->where('is_active', true)->firstOrFail();
        $result = $this->detection->processAssignment($assignment);

        $this->assertSame(0, $result->telemetrySampleCount);
        $this->assertSame(0, AssignmentEvaluationEvent::query()->where('event_code', DriverEvaluationEventCode::SPEEDING->value)->count());
    }

    public function test_harsh_braking_and_acceleration_and_sudden_stop(): void
    {
        $assignment = $this->makeAssignment();
        $base = Carbon::parse('2026-08-01 13:00:00');
        $this->seedLiveSpeedSeries($assignment, $base, [90, 50, 15, 0], 4);

        $this->detection->processAssignment($assignment);

        $codes = AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->pluck('event_code')
            ->map(fn ($c) => $c instanceof DriverEvaluationEventCode ? $c->value : $c)
            ->all();

        $this->assertTrue(
            in_array(DriverEvaluationEventCode::HARSH_BRAKING->value, $codes, true)
            || in_array(DriverEvaluationEventCode::SUDDEN_STOP->value, $codes, true)
        );

        $assignment2 = $this->makeAssignment([
            'assigned_at' => Carbon::parse('2026-08-01 14:00:00'),
            'ended_at' => Carbon::parse('2026-08-01 14:30:00'),
            'is_active' => false,
        ]);
        $this->seedLiveSpeedSeries($assignment2, Carbon::parse('2026-08-01 14:05:00'), [5, 35, 65, 95], 4);

        $logs = app(\App\Services\DriverEvaluation\Detection\EvaluationTelemetryLoader::class)
            ->loadForAssignment($assignment2, AssignmentEvaluationMode::LIVE);
        $this->assertGreaterThan(0, $logs->count(), 'Expected attributed live telemetry for assignment2');
        $accelCandidates = app(\App\Services\DriverEvaluation\Detection\MotionSpeedEvidenceAnalyzer::class)
            ->detectHarshAcceleration($logs, $assignment2->load(['vehicle', 'trailer']));
        $this->assertNotEmpty($accelCandidates, 'Analyzer should detect harsh acceleration candidates; log_count='.$logs->count());

        $this->detection->processAssignment($assignment2);

        $this->assertTrue(AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment2->id)
            ->where('event_code', DriverEvaluationEventCode::HARSH_ACCELERATION->value)
            ->exists());
    }

    public function test_invalid_time_delta_and_sparse_samples_do_not_create_false_motion_events(): void
    {
        $assignment = $this->makeAssignment();
        $base = Carbon::parse('2026-08-01 15:00:00');

        // Two samples only, gap 1s (below min interval) — insufficient.
        TrackingLog::query()->create($this->liveLogPayload($assignment, $base, 80));
        TrackingLog::query()->create($this->liveLogPayload($assignment, $base->copy()->addSecond(), 20));

        $this->detection->processAssignment($assignment);

        $this->assertSame(0, AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->whereIn('event_code', [
                DriverEvaluationEventCode::HARSH_BRAKING->value,
                DriverEvaluationEventCode::SUDDEN_STOP->value,
                DriverEvaluationEventCode::HARSH_ACCELERATION->value,
            ])
            ->count());
    }

    public function test_excessive_idle_and_prolonged_stop_and_short_stop_ignored(): void
    {
        $assignment = $this->makeAssignment();

        // Idle band speed 0 for 40s with 3+ samples.
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 16:00:00'), [0, 0, 0, 0], 12);
        $this->detection->processAssignment($assignment);
        $this->assertTrue(AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->where('event_code', DriverEvaluationEventCode::EXCESSIVE_IDLE->value)
            ->exists());

        $assignment2 = $this->makeAssignment([
            'assigned_at' => Carbon::parse('2026-08-01 17:00:00'),
            'ended_at' => Carbon::parse('2026-08-01 17:30:00'),
            'is_active' => false,
        ]);
        $this->seedLiveSpeedSeries($assignment2, Carbon::parse('2026-08-01 17:05:00'), [1.0, 1.5, 2.0, 1.2], 12);
        $this->detection->processAssignment($assignment2);
        $this->assertTrue(AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment2->id)
            ->where('event_code', DriverEvaluationEventCode::PROLONGED_STOP->value)
            ->exists());

        $assignment3 = $this->makeAssignment([
            'assigned_at' => Carbon::parse('2026-08-01 18:00:00'),
            'ended_at' => Carbon::parse('2026-08-01 18:30:00'),
            'is_active' => false,
        ]);
        $this->seedLiveSpeedSeries($assignment3, Carbon::parse('2026-08-01 18:05:00'), [2, 2], 5);
        $this->detection->processAssignment($assignment3);
        $this->assertSame(0, AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment3->id)
            ->where('event_code', DriverEvaluationEventCode::PROLONGED_STOP->value)
            ->count());
    }

    public function test_route_deviation_reuses_detector(): void
    {
        $assignment = $this->makeAssignment();
        $coords = [
            [13.4050, 52.5200],
            [13.4050, 52.5300],
            [13.4050, 52.5400],
        ];
        app(\App\Simulation\Routing\Application\Services\PlannedRouteCache::class)->storeIfAbsent(
            (string) $assignment->shipment_id,
            $coords,
            ['lat' => 52.5400, 'lng' => 13.4050],
            ['lat' => 52.5200, 'lng' => 13.4050],
            [
                'route' => [
                    'id' => 'test-route',
                    'geometry' => ['coordinates' => $coords],
                    'duration' => ['seconds' => 3600],
                ],
            ],
        );

        $base = Carbon::parse('2026-08-01 19:00:00');
        foreach ([0, 5, 10] as $i) {
            TrackingLog::query()->create($this->liveLogPayload(
                $assignment,
                $base->copy()->addSeconds($i),
                40,
                latitude: 52.5200,
                longitude: 13.4200,
            ));
        }

        $this->detection->processAssignment($assignment);

        $event = AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->where('event_code', DriverEvaluationEventCode::ROUTE_DEVIATION->value)
            ->first();
        $this->assertNotNull($event);
        $this->assertSame('route_deviation_detector', $event->evidence_metadata['geometry_source']);
    }

    public function test_timing_and_workflow_events(): void
    {
        $completed = Status::firstOrCreate(
            ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment'],
            ['name' => DriverAssignmentTransitionMap::COMPLETED, 'type' => 'assignment'],
        );

        $shipment = Shipment::factory()->create([
            'planned_pickup_date' => '2026-08-01',
            'planned_delivery_date' => '2026-08-02',
            'actual_delivery_date' => '2026-08-04',
        ]);

        app(\App\Simulation\Routing\Application\Services\PlannedRouteCache::class)->storeIfAbsent(
            (string) $shipment->id,
            [[13.4, 52.5], [13.5, 52.6]],
            ['lat' => 52.6, 'lng' => 13.5],
            ['lat' => 52.5, 'lng' => 13.4],
            [
                'route' => [
                    'id' => 'timing-route',
                    'geometry' => ['coordinates' => [[13.4, 52.5], [13.5, 52.6]]],
                    'duration' => ['seconds' => 3600],
                ],
            ],
        );

        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'status_id' => $completed->id,
            'assigned_at' => Carbon::parse('2026-08-03 08:00:00'),
            'ended_at' => Carbon::parse('2026-08-03 12:00:00'),
            'is_active' => false,
            'execution_phase' => DriverExecutionPhase::DELIVERY_ARRIVED,
        ]);

        $this->detection->processAssignment($assignment);

        $codes = AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->pluck('event_code')
            ->map(fn ($c) => $c instanceof DriverEvaluationEventCode ? $c->value : $c)
            ->all();

        $this->assertContains(DriverEvaluationEventCode::LATE_DEPARTURE->value, $codes);
        $this->assertContains(DriverEvaluationEventCode::LATE_ARRIVAL->value, $codes);
        $this->assertContains(DriverEvaluationEventCode::ABNORMAL_DURATION->value, $codes);
        $this->assertContains(DriverEvaluationEventCode::WORKFLOW_VIOLATION->value, $codes);
    }

    public function test_idempotent_duplicate_processing_and_no_scores(): void
    {
        $assignment = $this->makeAssignment();
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 20:00:00'), [120, 125, 130], 5);

        $first = $this->detection->processAssignment($assignment);
        $second = $this->detection->processAssignment($assignment);

        $speedingCount = AssignmentEvaluationEvent::query()
            ->where('assignment_id', $assignment->id)
            ->where('event_code', DriverEvaluationEventCode::SPEEDING->value)
            ->count();

        $this->assertSame(1, $speedingCount);
        $this->assertGreaterThan(0, $first->eventCount());
        $this->assertSame(0, count($second->eventsCreated));
        $this->assertSame(0, DriverScore::query()->count());
        $this->assertDatabaseMissing('driver_scores', ['driver_id' => $assignment->driver_id]);
    }

    public function test_finalized_evaluation_rejects_new_event_writes(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation->fresh());
        $this->persistence->finalizeCompleted($evaluation->fresh());

        $this->expectException(DriverEvaluationException::class);
        $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: DriverEvaluationEventCode::SPEEDING,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: 'eval.speeding',
            detectorVersion: '1.0.0',
            occurredAt: now(),
            trackingLogIds: [1],
        );
    }

    /** DRIVER-EVAL-08G FE-01: detection must not silently open an orphan mutable after finalize. */
    public function test_process_after_finalize_does_not_open_orphan_mutable_revision(): void
    {
        $assignment = $this->makeAssignment();
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 20:30:00'), [120, 125, 130], 5);
        $first = $this->detection->processAssignment($assignment, finalizeWhenDone: true);
        $this->assertTrue($first->evaluation->isFinalized());
        $this->assertSame(AssignmentEvaluationStatus::COMPLETED, $first->evaluation->fresh()->status);
        $preservedScore = $first->evaluation->overall_score;

        try {
            $this->detection->processAssignment($assignment);
            $this->fail('Expected DriverEvaluationException when opening mutable after finalize');
        } catch (DriverEvaluationException $e) {
            $this->assertSame(409, $e->statusCode);
        }

        $this->assertSame(1, AssignmentEvaluation::query()->where('assignment_id', $assignment->id)->count());
        $this->assertEquals(
            (float) $preservedScore,
            (float) $first->evaluation->fresh()->overall_score
        );
    }

    public function test_get_or_create_reuses_mutable_evaluation(): void
    {
        $assignment = $this->makeAssignment();
        $a = $this->persistence->getOrCreateMutableForAssignment($assignment);
        $b = $this->persistence->getOrCreateMutableForAssignment($assignment);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, AssignmentEvaluation::query()->where('assignment_id', $assignment->id)->count());
    }

    public function test_process_does_not_write_score_fields_on_events(): void
    {
        $assignment = $this->makeAssignment();
        $this->seedLiveSpeedSeries($assignment, Carbon::parse('2026-08-01 21:00:00'), [0, 0, 0, 0], 12);
        $this->detection->processAssignment($assignment);

        foreach (AssignmentEvaluationEvent::query()->where('assignment_id', $assignment->id)->get() as $event) {
            $meta = $event->evidence_metadata ?? [];
            $this->assertArrayNotHasKey('score', $meta);
            $this->assertArrayNotHasKey('penalty', $meta);
        }
        $this->assertNull(AssignmentEvaluation::query()->where('assignment_id', $assignment->id)->value('metadata')['score'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAssignment(array $overrides = []): Assignment
    {
        Status::firstOrCreate(
            ['name' => DriverAssignmentTransitionMap::IN_OPERATION, 'type' => 'assignment'],
            ['name' => DriverAssignmentTransitionMap::IN_OPERATION, 'type' => 'assignment'],
        );

        // Always create fresh shipment/driver/vehicle to avoid cross-assignment
        // ownership overlap (factory may otherwise reuse inRandomOrder rows).
        return Assignment::factory()->create(array_merge([
            'shipment_id' => Shipment::factory()->create()->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'assigned_at' => Carbon::parse('2026-08-01 08:00:00'),
            'ended_at' => null,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  list<float>  $speeds
     */
    private function seedLiveSpeedSeries(Assignment $assignment, Carbon $base, array $speeds, int $intervalSeconds): void
    {
        foreach ($speeds as $i => $speed) {
            TrackingLog::query()->create($this->liveLogPayload(
                $assignment,
                $base->copy()->addSeconds($i * $intervalSeconds),
                $speed,
            ));
        }
    }

    /**
     * @param  list<float>  $speeds
     */
    private function seedSimSpeedSeries(Assignment $assignment, Carbon $base, array $speeds, int $intervalSeconds): void
    {
        foreach ($speeds as $i => $speed) {
            $payload = $this->liveLogPayload(
                $assignment,
                $base->copy()->addSeconds($i * $intervalSeconds),
                $speed,
            );
            $payload['received_at'] = null;
            TrackingLog::query()->create($payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function liveLogPayload(
        Assignment $assignment,
        Carbon $at,
        float $speed,
        float $latitude = 52.52,
        float $longitude = 13.405,
    ): array {
        return [
            'shipment_id' => $assignment->shipment_id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => $speed,
            'heading' => 90,
            'accuracy' => 5,
            'recorded_at' => $at,
            'received_at' => $at->copy()->addSecond(),
        ];
    }
}
