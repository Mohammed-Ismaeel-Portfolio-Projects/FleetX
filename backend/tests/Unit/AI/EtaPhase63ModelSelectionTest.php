<?php

namespace Tests\Unit\AI;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class EtaPhase63ModelSelectionTest extends TestCase
{
    private string $aiBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiBaseDir = base_path('ai/eta');
    }

    public function test_phase_6_3_model_selection_script_executes_and_generates_artifacts(): void
    {
        $scriptPath = $this->aiBaseDir . '/evaluation/run_phase_6_3_model_selection.py';
        $this->assertFileExists($scriptPath);

        $command = "py -3.11 ai/eta/evaluation/run_phase_6_3_model_selection.py";
        $process = Process::path(base_path())->run($command);

        $this->assertTrue(
            $process->successful(),
            'Phase 6.3 model selection script execution failed: ' . $process->errorOutput()
        );

        $selJsonPath = $this->aiBaseDir . '/evaluation/phase_6_3_model_selection.json';
        $selCsvPath = $this->aiBaseDir . '/evaluation/phase_6_3_model_selection.csv';
        $stageJsonPath = $this->aiBaseDir . '/evaluation/phase_6_3_stage_analysis.json';
        $shipmentJsonPath = $this->aiBaseDir . '/evaluation/phase_6_3_shipment_analysis.json';
        $summaryMdPath = $this->aiBaseDir . '/evaluation/phase_6_3_summary.md';

        $this->assertFileExists($selJsonPath);
        $this->assertFileExists($selCsvPath);
        $this->assertFileExists($stageJsonPath);
        $this->assertFileExists($shipmentJsonPath);
        $this->assertFileExists($summaryMdPath);

        $plotsDir = $this->aiBaseDir . '/evaluation/plots';
        $this->assertFileExists($plotsDir . '/phase_6_3_model_comparison.png');
        $this->assertFileExists($plotsDir . '/phase_6_3_stage_comparison.png');
    }

    public function test_unified_benchmark_json_structure_and_production_artifact_protection(): void
    {
        $selJsonPath = $this->aiBaseDir . '/evaluation/phase_6_3_model_selection.json';
        $this->assertFileExists($selJsonPath);

        $sel = json_decode(file_get_contents($selJsonPath), true);
        $this->assertIsArray($sel);

        $requiredStrategies = [
            'Zero Residual (Baseline)',
            'Train Stage Mean Residual',
            'Prod HistGradientBoosting',
            'RandomForestRegressor',
            'Hybrid B (Weighted Ensemble)',
        ];

        foreach ($requiredStrategies as $stratKey) {
            $this->assertArrayHasKey($stratKey, $sel);
            $this->assertArrayHasKey('mae_seconds', $sel[$stratKey]);
            $this->assertArrayHasKey('rmse_seconds', $sel[$stratKey]);
            $this->assertArrayHasKey('total_time_r2', $sel[$stratKey]);
            $this->assertGreaterThan(0, $sel[$stratKey]['mae_seconds']);
        }

        // Verify production model artifact was NOT overwritten
        $prodModelPath = $this->aiBaseDir . '/models/eta_residual_model.joblib';
        $this->assertFileExists($prodModelPath);
    }
}
