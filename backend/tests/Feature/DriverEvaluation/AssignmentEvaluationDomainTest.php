<?php

declare(strict_types=1);

namespace Tests\Feature\DriverEvaluation;

use App\Enums\AssignmentEvaluationEvidenceQuality;
use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\AssignmentEvaluationEvent;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverEvaluation\AssignmentEvaluationPersistenceService;
use App\Services\DriverEvaluation\AssignmentTelemetryAttributionService;
use App\Services\DriverEvaluation\Exceptions\DriverEvaluationException;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * DRIVER-EVAL-02 — domain foundation / data contract tests.
 * No scoring. No detection engine.
 */
class AssignmentEvaluationDomainTest extends TestCase
{
    use RefreshDatabase;

    private AssignmentEvaluationPersistenceService $persistence;

    private AssignmentTelemetryAttributionService $attribution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->persistence = app(AssignmentEvaluationPersistenceService::class);
        $this->attribution = app(AssignmentTelemetryAttributionService::class);
    }

    public function test_evaluation_belongs_to_assignment_and_preserves_identity_snapshot(): void
    {
        $assignment = $this->makeAssignment();

        $evaluation = $this->persistence->createForAssignment($assignment);

        $this->assertSame($assignment->id, $evaluation->assignment_id);
        $this->assertSame((string) $assignment->shipment_id, (string) $evaluation->shipment_id);
        $this->assertSame((int) $assignment->driver_id, (int) $evaluation->driver_id);
        $this->assertSame((string) $assignment->vehicle_id, (string) $evaluation->vehicle_id);
        $this->assertSame(AssignmentEvaluationStatus::PENDING, $evaluation->status);
        $this->assertSame(AssignmentEvaluationMode::LIVE, $evaluation->evaluation_mode);
        $this->assertTrue($assignment->evaluations()->whereKey($evaluation->id)->exists());
    }

    public function test_event_belongs_to_evaluation_and_persists_evidence_references(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation);

        $result = $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: DriverEvaluationEventCode::HARSH_BRAKING,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: 'eval.motion',
            detectorVersion: '1.0.0',
            occurredAt: now(),
            trackingLogIds: [101, 102, 103],
            evidenceMetadata: ['peak_decel_ms2' => 2.4],
            windowStart: now()->subSeconds(20),
            windowEnd: now(),
        );

        $this->assertTrue($result['created']);
        /** @var AssignmentEvaluationEvent $event */
        $event = $result['event'];
        $this->assertSame($evaluation->id, $event->assignment_evaluation_id);
        $this->assertSame([101, 102, 103], $event->evidence_tracking_log_ids);
        $this->assertSame('eval.motion', $event->detector_code);
        $this->assertSame('1.0.0', $event->detector_version);
        $this->assertSame(DriverEvaluationEvidenceSource::LIVE, $event->evidence_source);
        $this->assertSame(['peak_decel_ms2' => 2.4], $event->evidence_metadata);
    }

    public function test_live_and_simulation_sources_are_distinguishable(): void
    {
        $shipment = Shipment::factory()->create();

        $live = TrackingLog::query()->create([
            'shipment_id' => $shipment->id,
            'latitude' => 52.52,
            'longitude' => 13.405,
            'speed' => 40,
            'heading' => 90,
            'accuracy' => 5,
            'recorded_at' => now(),
            'received_at' => now(),
        ]);
        $sim = TrackingLog::query()->create([
            'shipment_id' => $shipment->id,
            'latitude' => 52.53,
            'longitude' => 13.406,
            'speed' => 41,
            'heading' => 91,
            'accuracy' => 8,
            'recorded_at' => now(),
            'received_at' => null,
        ]);

        $this->assertSame(
            DriverEvaluationEvidenceSource::LIVE,
            $this->attribution->classifyEvidenceSource($live),
        );
        $this->assertSame(
            DriverEvaluationEvidenceSource::SIMULATION,
            $this->attribution->classifyEvidenceSource($sim),
        );
    }

    public function test_historical_attribution_uses_assignment_time_bounds(): void
    {
        $shipment = Shipment::factory()->create();
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_at' => Carbon::parse('2026-08-01 10:00:00'),
            'ended_at' => Carbon::parse('2026-08-01 18:00:00'),
            'is_active' => false,
        ]);

        $log = TrackingLog::query()->create([
            'shipment_id' => $shipment->id,
            'latitude' => 52.52,
            'longitude' => 13.405,
            'speed' => 50,
            'heading' => 10,
            'accuracy' => 5,
            'recorded_at' => Carbon::parse('2026-08-01 12:00:00'),
            'received_at' => Carbon::parse('2026-08-01 12:00:01'),
        ]);

        $result = $this->attribution->attributeTrackingLog($log);

        $this->assertTrue($result->isAttributed());
        $this->assertSame($assignment->id, $result->assignment->id);
        $this->assertSame($driver->id, $result->assignment->driver_id);
    }

    public function test_reassignment_does_not_attribute_old_telemetry_to_new_driver(): void
    {
        $shipment = Shipment::factory()->create();
        $driverA = Driver::factory()->create();
        $driverB = Driver::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();

        Assignment::factory()->create([
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

        $oldLog = TrackingLog::query()->create([
            'shipment_id' => $shipment->id,
            'latitude' => 52.52,
            'longitude' => 13.405,
            'speed' => 45,
            'heading' => 20,
            'accuracy' => 5,
            'recorded_at' => Carbon::parse('2026-08-01 10:00:00'),
            'received_at' => now(),
        ]);

        $result = $this->attribution->attributeTrackingLog($oldLog);

        $this->assertTrue($result->isAttributed());
        $this->assertSame($driverA->id, $result->assignment->driver_id);
        $this->assertNotSame($driverB->id, $result->assignment->driver_id);
    }

    public function test_ambiguous_overlapping_windows_are_rejected(): void
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

        $result = $this->attribution->attributeShipmentAt((string) $shipment->id, $t0);

        $this->assertSame('ambiguous', $result->status);
        $this->assertFalse($result->isAttributed());
        $this->assertGreaterThan(1, $result->matchCount);
    }

    public function test_duplicate_event_insertion_is_idempotent(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation->fresh());

        $first = $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: DriverEvaluationEventCode::SPEEDING,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: 'eval.speed',
            detectorVersion: '1.0.0',
            occurredAt: now(),
            trackingLogIds: [10, 11],
            windowStart: now()->subMinute(),
            windowEnd: now(),
        );
        $second = $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: DriverEvaluationEventCode::SPEEDING,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: 'eval.speed',
            detectorVersion: '1.0.0',
            occurredAt: now(),
            trackingLogIds: [10, 11],
            windowStart: now()->subMinute(),
            windowEnd: now(),
        );

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['event']->id, $second['event']->id);
        $this->assertSame(1, AssignmentEvaluationEvent::query()->count());
    }

    public function test_detection_version_is_persisted(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation->fresh());

        $result = $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: DriverEvaluationEventCode::ROUTE_DEVIATION,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: 'eval.route_deviation',
            detectorVersion: '2.1.0',
            occurredAt: now(),
            trackingLogIds: [55],
        );

        $this->assertSame('eval.route_deviation', $result['event']->detector_code);
        $this->assertSame('2.1.0', $result['event']->detector_version);
    }

    public function test_finalized_evaluation_cannot_be_silently_mutated(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->finalizeCompleted($evaluation->fresh());

        $this->expectException(DriverEvaluationException::class);
        $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: DriverEvaluationEventCode::SPEEDING,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: 'eval.speed',
            detectorVersion: '1.0.0',
            occurredAt: now(),
            trackingLogIds: [1],
        );
    }

    public function test_missing_telemetry_marks_incomplete_not_a_negative_score(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);

        $final = $this->persistence->markIncomplete(
            $evaluation,
            AssignmentEvaluationEvidenceQuality::INSUFFICIENT,
            'No live telemetry samples.',
        );

        $this->assertSame(AssignmentEvaluationStatus::INCOMPLETE, $final->status);
        $this->assertSame(AssignmentEvaluationEvidenceQuality::INSUFFICIENT, $final->evidence_quality);
        $this->assertNotNull($final->finalized_at);
        $this->assertArrayNotHasKey('score', $final->metadata ?? []);
        $this->assertSame('No live telemetry samples.', $final->metadata['incomplete_reason']);
        $this->assertDatabaseMissing('driver_scores', [
            'driver_id' => $assignment->driver_id,
        ]);
    }

    public function test_simulation_evidence_rejected_on_live_evaluation_mode(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment, AssignmentEvaluationMode::LIVE);
        $this->persistence->markProcessing($evaluation->fresh());

        $this->expectException(DriverEvaluationException::class);
        $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: DriverEvaluationEventCode::SPEEDING,
            evidenceSource: DriverEvaluationEvidenceSource::SIMULATION,
            detectorCode: 'eval.speed',
            detectorVersion: '1.0.0',
            occurredAt: now(),
            trackingLogIds: [1],
        );
    }

    public function test_authorization_admin_dispatcher_driver_client_boundaries(): void
    {
        $assignment = $this->makeAssignment(['is_active' => false, 'ended_at' => now()]);
        $evaluation = $this->persistence->createForAssignment($assignment);

        $admin = $this->userWithRole('admin');
        $dispatcher = $this->userWithRole('dispatcher');
        $client = $this->userWithRole('client');

        $ownerUser = $this->userWithRole('driver');
        $ownerDriver = Driver::factory()->create(['user_id' => $ownerUser->id]);
        $ownerEval = $this->persistence->createForAssignment(
            $this->makeAssignment([
                'driver_id' => $ownerDriver->id,
                'is_active' => false,
                'ended_at' => now(),
            ]),
        );

        $otherUser = $this->userWithRole('driver');
        Driver::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('driverEvaluation.viewAny'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('driverEvaluation.viewAny'));
        $this->assertFalse(Gate::forUser($client)->allows('driverEvaluation.viewAny'));
        $this->assertFalse(Gate::forUser($ownerUser)->allows('driverEvaluation.viewAny'));

        $this->assertTrue(Gate::forUser($admin)->allows('view', $evaluation));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('view', $evaluation));
        $this->assertFalse(Gate::forUser($client)->allows('view', $evaluation));

        $this->assertTrue(Gate::forUser($ownerUser)->allows('view', $ownerEval));
        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $ownerEval));

        $this->assertTrue(Gate::forUser($admin)->allows('driverEvaluation.manage'));
        $this->assertFalse(Gate::forUser($dispatcher)->allows('driverEvaluation.manage'));
        $this->assertFalse(Gate::forUser($client)->allows('driverEvaluation.manage'));
    }

    public function test_unsupported_future_event_code_is_rejected(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation->fresh());

        $this->expectException(DriverEvaluationException::class);
        $this->persistence->recordEvent(
            evaluation: $evaluation->fresh(),
            eventCode: DriverEvaluationEventCode::HARSH_CORNERING,
            evidenceSource: DriverEvaluationEvidenceSource::LIVE,
            detectorCode: 'eval.corner',
            detectorVersion: '1.0.0',
            occurredAt: now(),
        );
    }

    /** DRIVER-EVAL-08G FE-01: getOrCreate must not open orphan mutable after finalize. */
    public function test_get_or_create_mutable_rejects_after_finalized_evaluation(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation->fresh());
        $evaluation->fresh()->update([
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'finalized_at' => now(),
            'overall_score' => 88.5,
        ]);

        $this->expectException(DriverEvaluationException::class);
        $this->persistence->getOrCreateMutableForAssignment($assignment->fresh());
    }

    /** DRIVER-EVAL-08G FE-02: metadata / event writes require mutability. */
    public function test_assert_mutable_blocks_metadata_path_after_finalize(): void
    {
        $assignment = $this->makeAssignment();
        $evaluation = $this->persistence->createForAssignment($assignment);
        $this->persistence->markProcessing($evaluation->fresh());
        $evaluation->fresh()->update([
            'status' => AssignmentEvaluationStatus::COMPLETED,
            'finalized_at' => now(),
            'overall_score' => 90.0,
        ]);

        $this->expectException(DriverEvaluationException::class);
        $this->persistence->assertMutable($evaluation->fresh());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAssignment(array $overrides = []): Assignment
    {
        Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment'],
        );

        return Assignment::factory()->create(array_merge([
            'assigned_at' => now()->subHours(2),
            'ended_at' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }
}
