<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestTrackingQuery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-tracking-query';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $states = \DB::table('tracking_states')
            ->select('id', 'assignment_id', 'latitude', 'longitude', 'updated_at')
            ->limit(10)
            ->get();

        $this->info('=== CURRENT TRACKING STATES ===');
        foreach ($states as $state) {
            $this->line("ID: {$state->id}, Assignment: {$state->assignment_id}, Lat: {$state->latitude}, Lng: {$state->longitude}, Updated: {$state->updated_at}");
        }

        // Check for orphan tracking states
        $totalStates = \DB::table('tracking_states')->count();
        $activeAssignments = \DB::table('assignments')->where('is_active', true)->count();
        $orphanStates = \DB::table('tracking_states')
            ->leftJoin('assignments', 'tracking_states.assignment_id', '=', 'assignments.id')
            ->whereNull('assignments.id')
            ->orWhere('assignments.is_active', false)
            ->count();

        $this->newLine();
        $this->info('=== TRACKING STATE ANALYSIS ===');
        $this->line("Total Tracking States: {$totalStates}");
        $this->line("Active Assignments: {$activeAssignments}");
        $this->line("Orphan/Inactive Tracking States: {$orphanStates}");
        
        if ($orphanStates > 0) {
            $this->error("FOUND {$orphanStates} orphan/inactive tracking states!");
        } else {
            $this->info("No orphan tracking states found.");
        }

        return 0;
    }
}
