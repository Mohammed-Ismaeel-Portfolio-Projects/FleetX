<?php

namespace Tests\Unit\Support;

use Tests\TestCase;

/**
 * D15-C architecture guards — live observer only; no second movement engine.
 */
class DriverMotionSafetyArchitectureTest extends TestCase
{
    public function test_no_motion_evaluator_hooked_on_shared_tracking_writer(): void
    {
        $writer = file_get_contents(app_path('Actions/Tracking/CreateTrackingLogAction.php'));
        $this->assertIsString($writer);
        $this->assertStringNotContainsString('DriverMotionSafetyPolicy', $writer);
        $this->assertStringNotContainsString('EvaluateDriverMotion', $writer);
        $this->assertStringNotContainsString('EvaluateDriverSpeedSafetyAction', $writer);
    }

    public function test_driver_ingest_evaluates_motion_after_successful_live_ingest(): void
    {
        $ingest = file_get_contents(app_path('Actions/Driver/SubmitDriverTrackingAction.php'));
        $this->assertIsString($ingest);
        $this->assertStringContainsString('EvaluateDriverSpeedSafetyAction', $ingest);
        $this->assertStringContainsString('EvaluateDriverMotionSafetyAction', $ingest);

        $speedPos = strpos($ingest, 'EvaluateDriverSpeedSafetyAction');
        $motionPos = strpos($ingest, 'EvaluateDriverMotionSafetyAction');
        $this->assertNotFalse($speedPos);
        $this->assertNotFalse($motionPos);
        $this->assertGreaterThan($speedPos, $motionPos);
    }

    public function test_motion_evaluator_is_an_observer_not_a_simulator(): void
    {
        $this->assertFileExists(app_path('Actions/Driver/EvaluateDriverMotionSafetyAction.php'));
        $eval = file_get_contents(app_path('Actions/Driver/EvaluateDriverMotionSafetyAction.php'));
        $this->assertIsString($eval);
        $this->assertStringContainsString('whereNotNull(\'received_at\')', $eval);
        $this->assertStringContainsString('lockForUpdate', $eval);
        $this->assertStringContainsString('DriverMotionKinematics', $eval);
        $this->assertStringContainsString('freightClassForAssignment', $eval);
        $this->assertStringContainsString('Sharp slowdown detected', $eval);
        $this->assertStringContainsString('Rapid stop detected', $eval);
        $this->assertStringNotContainsString('TrackingSimulatorService', $eval);
        $this->assertStringNotContainsString('EnvironmentEngine', $eval);
        $this->assertStringNotContainsString('DriverIntelligenceEngine', $eval);
        $this->assertStringNotContainsString('VehicleHealthEngine', $eval);
        $this->assertStringNotContainsString('VehicleDynamicsEngine', $eval);
        $this->assertStringNotContainsString('speed_delta_kmh', $eval);
        $this->assertStringNotContainsString('simulation:telemetry', $eval);
        $this->assertStringContainsString('harshBrakeThresholdMs2', $eval);
    }

    public function test_d15c_notification_codes_are_warning_motion_events(): void
    {
        $codes = file_get_contents(app_path('Support/DriverNotificationCode.php'));
        $this->assertIsString($codes);
        $this->assertStringContainsString("safety.harsh_braking", $codes);
        $this->assertStringContainsString("safety.sudden_stop", $codes);
        $this->assertStringContainsString('SPEED_EXCESS', $codes);

        $policy = file_get_contents(app_path('Support/DriverMotionSafetyPolicy.php'));
        $this->assertIsString($policy);
        $this->assertStringNotContainsString('DriverNotificationWriter', $policy);
        $this->assertStringNotContainsString('VehicleDynamicsEngine', $policy);
        $this->assertStringNotContainsString('EnvironmentEngine', $policy);
        $this->assertStringNotContainsString('simulation:telemetry', $policy);
    }

    public function test_kinematics_does_not_call_simulation_runtime(): void
    {
        $kin = file_get_contents(app_path('Support/DriverMotionKinematics.php'));
        $this->assertIsString($kin);
        $this->assertStringNotContainsString('use App\\Simulation\\Dynamics', $kin);
        $this->assertStringNotContainsString('computeTick', $kin);
        $this->assertStringContainsString('kmhToMetersPerSecond', $kin);
    }

    public function test_no_d15_migration_files(): void
    {
        $files = glob(database_path('migrations').DIRECTORY_SEPARATOR.'*.php') ?: [];
        foreach ($files as $file) {
            $base = basename($file);
            $this->assertDoesNotMatchRegularExpression('/d15|motion_event|harsh_brake|acceleration/i', $base);
        }
    }

    public function test_summary_counts_use_grouped_inbox_query(): void
    {
        $summary = file_get_contents(app_path('Actions/Driver/GetDriverAssignmentSummaryAction.php'));
        $this->assertIsString($summary);
        $this->assertStringContainsString('groupBy(\'code\')', $summary);
        $this->assertStringContainsString('HARSH_BRAKING', $summary);
        $this->assertStringContainsString('SUDDEN_STOP', $summary);
        $this->assertStringContainsString('SPEED_EXCESS', $summary);
        $this->assertStringNotContainsString('VehicleDynamicsEngine', $summary);
        $this->assertStringNotContainsString('TrackingLog', $summary);
    }

    public function test_motion_evaluator_does_not_mutate_availability_or_assignment_state(): void
    {
        $eval = file_get_contents(app_path('Actions/Driver/EvaluateDriverMotionSafetyAction.php'));
        $this->assertIsString($eval);
        $this->assertStringNotContainsString('GetDriverAvailabilityAction', $eval);
        $this->assertStringNotContainsString("'status_id' =>", $eval);
        $this->assertStringNotContainsString("'is_active' =>", $eval);
        $this->assertStringNotContainsString("'execution_phase' =>", $eval);

        $push = file_get_contents(app_path('Support/DriverPushDispatcher.php'));
        $this->assertIsString($push);
        $this->assertStringContainsString('forDriver((int) $notification->driver_id)', $push);
        $this->assertStringContainsString('dispatchBestEffort', $push);
    }
}
