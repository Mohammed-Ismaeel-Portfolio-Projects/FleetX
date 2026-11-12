<?php

namespace Tests\Unit\Tracking;

use App\Actions\Tracking\QueryLiveFleetSnapshotAction;
use App\Models\Assignment;
use App\Models\TrackingState;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Lightweight structural tests — full DB factories vary by environment.
 * Prefers asserting action shape when assignments exist; otherwise skips.
 */
class QueryLiveFleetSnapshotActionTest extends TestCase
{
    public function test_returns_unique_vehicle_ids_for_active_assignments(): void
    {
        Cache::flush();

        $activeCount = Assignment::query()->active()->whereNotNull('vehicle_id')->count();
        if ($activeCount === 0) {
            $this->markTestSkipped('No active assignments in database');
        }

        $result = app(QueryLiveFleetSnapshotAction::class)->execute();

        $this->assertTrue($result->success);
        $vehicles = $result->data['vehicles'] ?? [];
        $this->assertIsArray($vehicles);

        $vehicleIds = array_map(fn ($v) => $v['vehicle_id'], $vehicles);
        $this->assertSame(count($vehicleIds), count(array_unique($vehicleIds)));

        $uniqueActiveVehicles = Assignment::query()
            ->active()
            ->whereNotNull('vehicle_id')
            ->pluck('vehicle_id')
            ->unique()
            ->count();

        $this->assertCount($uniqueActiveVehicles, $vehicles);
        $this->assertSame($uniqueActiveVehicles, $result->data['meta']['count'] ?? null);

        foreach ($vehicles as $row) {
            $this->assertArrayHasKey('vehicle_id', $row);
            $this->assertArrayHasKey('assignment_id', $row);
            $this->assertArrayHasKey('shipment_id', $row);
            $this->assertArrayHasKey('telemetry', $row);
            $this->assertArrayHasKey('tracking_state', $row);
            $this->assertArrayHasKey('route_progress', $row);
            $this->assertTrue((bool) ($row['assignment']['is_active'] ?? false));
        }
    }

    public function test_excludes_inactive_assignment_tracking_states(): void
    {
        $inactiveStates = TrackingState::query()
            ->whereIn('assignment_id', Assignment::query()->where('is_active', false)->pluck('id'))
            ->count();

        if ($inactiveStates === 0) {
            $this->markTestSkipped('No inactive-assignment tracking states to assert exclusion');
        }

        $result = app(QueryLiveFleetSnapshotAction::class)->execute();
        $this->assertTrue($result->success);

        $returnedAssignmentIds = collect($result->data['vehicles'] ?? [])
            ->pluck('assignment_id')
            ->all();

        $inactiveIds = Assignment::query()->where('is_active', false)->pluck('id')->map(fn ($id) => (string) $id)->all();

        foreach ($returnedAssignmentIds as $id) {
            $this->assertNotContains((string) $id, $inactiveIds);
        }
    }
}
