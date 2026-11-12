<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Runtime\SimulationRuntime;
use App\Simulation\Session\SimulationClock;
use App\Simulation\Session\SimulationSession;
use App\Simulation\Session\SimulationSessionManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SimulationSessionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_clock_wait_respects_time_scale(): void
    {
        $clock = new SimulationClock(60, 1.0);
        $this->assertSame(60.0, $clock->waitSeconds());

        $clock->setTimeScale(2.0);
        $this->assertSame(30.0, $clock->waitSeconds());

        $clock->setTimeScale(10.0);
        $this->assertSame(6.0, $clock->waitSeconds());
    }

    public function test_clock_rejects_invalid_time_scale(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SimulationClock())->setTimeScale(3.0);
    }

    public function test_start_runs_single_cycle_then_stops_via_callback(): void
    {
        Log::spy();

        $runtime = Mockery::mock(SimulationRuntime::class);
        $runtime->shouldReceive('runSingleCycle')
            ->once()
            ->andReturn([
                'vehicles_processed' => 1,
                'tracking_logs_created' => 1,
                'vehicles_moved' => 1,
                'idle_vehicles' => 0,
                'stopped_vehicles' => 0,
                'offline_vehicles' => 0,
            ]);

        $manager = new SimulationSessionManager($runtime, new SimulationClock());

        $manager->start(
            tickIntervalSeconds: 1,
            timeScale: 10.0,
            afterTick: function (SimulationSession $session) use ($manager): void {
                $this->assertSame(1, $session->tickCount);
                $manager->stop();
            }
        );

        $final = $manager->status();
        $this->assertNotNull($final);
        $this->assertSame(SimulationSession::STATUS_STOPPED, $final->status);
        $this->assertSame(1, $final->tickCount);
    }

    public function test_pause_and_resume_update_session_status(): void
    {
        Log::spy();
        Cache::put(SimulationSessionManager::CACHE_KEY, (new SimulationSession(
            sessionId: 'sess-1',
            startedAt: now()->toIso8601String(),
            status: SimulationSession::STATUS_RUNNING,
            tickCount: 3,
            tickIntervalSeconds: 60,
            timeScale: 1.0,
            lastTick: now()->toIso8601String(),
            pid: getmypid(),
        ))->toArray(), 3600);

        $runtime = Mockery::mock(SimulationRuntime::class);
        $manager = new SimulationSessionManager($runtime, new SimulationClock());

        $paused = $manager->pause();
        $this->assertSame(SimulationSession::STATUS_PAUSED, $paused->status);
        $this->assertNotNull($paused->pausedAt);

        $resumed = $manager->resume();
        $this->assertSame(SimulationSession::STATUS_RUNNING, $resumed->status);
        $this->assertNull($resumed->pausedAt);
    }
}
