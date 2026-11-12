<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupTrackingStates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-tracking-states {--force : Skip confirmation} {--regenerate : Delete all tracking states for regeneration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphan and inactive tracking states to ensure data consistency';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== TRACKING STATE CLEANUP ===');
        $this->newLine();

        // Get current counts
        $totalStates = \DB::table('tracking_states')->count();
        $activeAssignments = \DB::table('assignments')->where('is_active', true)->count();

        $this->info("Current Tracking States: {$totalStates}");
        $this->info("Active Assignments: {$activeAssignments}");
        $this->newLine();

        if ($this->option('regenerate')) {
            // Delete all tracking states for regeneration
            if ($this->option('force') || $this->confirm('Do you want to delete ALL tracking states for regeneration?')) {
                $deleted = \DB::table('tracking_states')->delete();
                $this->info("Deleted {$deleted} tracking states for regeneration");
                $this->info("Simulator will regenerate them with new logistics points");
            } else {
                $this->info('Regeneration cancelled');
            }
        } else {
            if ($this->option('force') || $this->confirm('Do you want to proceed with cleanup?')) {
                // Delete tracking states that belong to inactive or non-existent assignments
                $deleted = \DB::table('tracking_states')
                    ->whereNotIn('assignment_id', function ($query) {
                        $query->select('id')
                            ->from('assignments')
                            ->where('is_active', true);
                    })
                    ->delete();

                $this->info("Deleted {$deleted} orphan/inactive tracking states");

                // Verify cleanup
                $newTotalStates = \DB::table('tracking_states')->count();
                $this->newLine();
                $this->info("New Tracking States count: {$newTotalStates}");
                $this->info("Active Assignments count: {$activeAssignments}");

                if ($newTotalStates === $activeAssignments) {
                    $this->info('✓ Tracking states count matches active assignments count');
                } else {
                    $this->error('✗ Tracking states count does not match active assignments count');
                }
            } else {
                $this->info('Cleanup cancelled');
            }
        }

        return 0;
    }
}
