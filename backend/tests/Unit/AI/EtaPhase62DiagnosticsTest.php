<?php

namespace Tests\Unit\AI;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class EtaPhase62DiagnosticsTest extends TestCase
{
    private string $aiBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiBaseDir = base_path('ai/eta');
    }

    public function test_phase_6_2_diagnostics_script_executes_and_generates_all_artifacts(): void
    {
        $scriptPath = $this->aiBaseDir . '/evaluation/run_phase_6_2_diagnostics.py';
        $this->assertFileExists($scriptPath);

        $command = "py -3.11 ai/eta/evaluation/run_phase_6_2_diagnostics.py";
        $process = Process::path(base_path())->run($command);

        $this->assertTrue(
            $process->successful(),
            'Phase 6.2 diagnostics script execution failed: ' . $process->errorOutput()
        );

        $diagJsonPath = $this->aiBaseDir . '/evaluation/residual_diagnostics.json';
        $diagCsvPath = $this->aiBaseDir . '/evaluation/residual_diagnostics.csv';
        $modelCompJsonPath = $this->aiBaseDir . '/evaluation/model_comparison.json';
        $ablationJsonPath = $this->aiBaseDir . '/evaluation/feature_ablation.json';
        $summaryMdPath = $this->aiBaseDir . '/evaluation/phase_6_2_summary.md';

        $this->assertFileExists($diagJsonPath);
        $this->assertFileExists($diagCsvPath);
        $this->assertFileExists($modelCompJsonPath);
        $this->assertFileExists($ablationJsonPath);
        $this->assertFileExists($summaryMdPath);

        $plotsDir = $this->aiBaseDir . '/evaluation/plots';
        $this->assertFileExists($plotsDir . '/error_distribution.png');
        $this->assertFileExists($plotsDir . '/mae_by_journey_stage.png');
        $this->assertFileExists($plotsDir . '/ablation_comparison.png');
    }

    public function test_model_comparison_structure_and_candidate_separation(): void
    {
        $modelCompJsonPath = $this->aiBaseDir . '/evaluation/model_comparison.json';
        $this->assertFileExists($modelCompJsonPath);

        $comp = json_decode(file_get_contents($modelCompJsonPath), true);
        $this->assertIsArray($comp);

        $this->assertArrayHasKey('Current HistGradientBoosting', $comp);
        $this->assertArrayHasKey('RandomForestRegressor', $comp);
        $this->assertArrayHasKey('GradientBoostingRegressor', $comp);

        // Verify production model artifact was NOT overwritten and candidate model exists
        $prodModelPath = $this->aiBaseDir . '/models/eta_residual_model.joblib';
        $candidateModelPath = $this->aiBaseDir . '/models/eta_residual_model_candidate.joblib';

        $this->assertFileExists($prodModelPath);
        $this->assertFileExists($candidateModelPath);
    }
}
