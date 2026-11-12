<?php

namespace Tests\Unit\AI;

use App\Services\Eta\FLeetXEnhancedETAService;
use App\Simulation\Progress\RouteProgressEngine;
use Illuminate\Support\Facades\Process;

use Tests\TestCase;

class EtaResidualModelTrainingTest extends TestCase
{
    private string $aiBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiBaseDir = base_path('ai/eta');
    }

    public function test_ml_pipeline_executes_and_generates_all_required_artifacts(): void
    {
        $scriptPath = $this->aiBaseDir . '/training/train_residual_model.py';
        $this->assertFileExists($scriptPath);

        // Execute Python training pipeline
        $command = "py -3.11 ai/eta/training/train_residual_model.py";
        $process = Process::path(base_path())->run($command);

        $this->assertTrue(
            $process->successful(),
            'Python training pipeline execution failed: ' . $process->errorOutput()
        );

        // Verify generated artifacts
        $modelPath = $this->aiBaseDir . '/models/eta_residual_model.joblib';
        $manifestPath = $this->aiBaseDir . '/models/feature_manifest.json';
        $metadataPath = $this->aiBaseDir . '/models/model_metadata.json';
        $metricsPath = $this->aiBaseDir . '/evaluation/baseline_vs_ai_metrics.json';
        $bucketsPath = $this->aiBaseDir . '/evaluation/progress_bucket_analysis.json';
        $reportCsvPath = $this->aiBaseDir . '/evaluation/evaluation_report.csv';

        $this->assertFileExists($modelPath);
        $this->assertFileExists($manifestPath);
        $this->assertFileExists($metadataPath);
        $this->assertFileExists($metricsPath);
        $this->assertFileExists($bucketsPath);
        $this->assertFileExists($reportCsvPath);
    }

    public function test_feature_manifest_contains_zero_leakage_features(): void
    {
        $manifestPath = $this->aiBaseDir . '/models/feature_manifest.json';
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);

        $featureNames = $manifest['feature_names'];
        $excludedLeakageCols = $manifest['excluded_leakage_cols'];

        // Verify none of the excluded leakage columns are present in feature_names
        foreach ($excludedLeakageCols as $leakageCol) {
            $this->assertNotContains(
                $leakageCol,
                $featureNames,
                "Data leakage violation: Forbidden target/future feature '{$leakageCol}' found in feature manifest!"
            );
        }
    }

    public function test_evaluation_metrics_and_mae_improvement_are_calculated(): void
    {
        $metricsPath = $this->aiBaseDir . '/evaluation/baseline_vs_ai_metrics.json';
        $this->assertFileExists($metricsPath);

        $metrics = json_decode(file_get_contents($metricsPath), true);
        $this->assertIsArray($metrics);

        $this->assertArrayHasKey('baseline_mae_seconds', $metrics);
        $this->assertArrayHasKey('ai_mae_seconds', $metrics);
        $this->assertArrayHasKey('mae_improvement_seconds', $metrics);
        $this->assertArrayHasKey('mae_improvement_percentage', $metrics);
        $this->assertArrayHasKey('baseline_rmse_seconds', $metrics);
        $this->assertArrayHasKey('ai_rmse_seconds', $metrics);

        $baselineMae = $metrics['baseline_mae_seconds'];
        $aiMae = $metrics['ai_mae_seconds'];
        $maeImpPct = $metrics['mae_improvement_percentage'];

        $this->assertGreaterThan(0, $baselineMae);
        $this->assertGreaterThan(0, $aiMae);

        // Verify mathematical consistency: MAE Improvement % = ((baseline - ai) / baseline) * 100
        $expectedPct = round((($baselineMae - $aiMae) / $baselineMae) * 100.0, 2);
        $this->assertEquals($expectedPct, $maeImpPct);
    }

    public function test_progress_bucket_analysis_is_produced(): void
    {
        $bucketsPath = $this->aiBaseDir . '/evaluation/progress_bucket_analysis.json';
        $this->assertFileExists($bucketsPath);

        $analysis = json_decode(file_get_contents($bucketsPath), true);
        $this->assertIsArray($analysis);

        $expectedBuckets = ['0-20%', '20-40%', '40-60%', '60-80%', '80-100%'];
        foreach ($expectedBuckets as $bucketLabel) {
            $this->assertArrayHasKey($bucketLabel, $analysis);
            $this->assertArrayHasKey('sample_count', $analysis[$bucketLabel]);
            $this->assertArrayHasKey('baseline_mae_seconds', $analysis[$bucketLabel]);
            $this->assertArrayHasKey('ai_mae_seconds', $analysis[$bucketLabel]);
            $this->assertArrayHasKey('mae_improvement_pct', $analysis[$bucketLabel]);
        }
    }

    public function test_evaluation_report_csv_guarantees_non_negative_ai_eta(): void
    {
        $reportCsvPath = $this->aiBaseDir . '/evaluation/evaluation_report.csv';
        $this->assertFileExists($reportCsvPath);

        $file = fopen($reportCsvPath, 'r');
        $this->assertNotFalse($file);

        $headers = fgetcsv($file);
        $aiEtaIndex = array_search('ai_remaining_seconds', $headers, true);
        $this->assertNotFalse($aiEtaIndex);

        $rowCount = 0;
        while (($row = fgetcsv($file)) !== false) {
            $rowCount++;
            $aiEta = (float) $row[$aiEtaIndex];
            $this->assertGreaterThanOrEqual(
                0.0,
                $aiEta,
                "Safety Violation: AI ETA prediction is negative ({$aiEta}) on row {$rowCount}!"
            );
        }
        fclose($file);

        $this->assertGreaterThan(0, $rowCount);
    }

    public function test_live_baseline_eta_service_remains_unmodified_and_functional(): void
    {
        $etaService = app(FLeetXEnhancedETAService::class);
        $this->assertNotNull($etaService);

        $shipment = new \App\Models\Shipment(['id' => '00000000-0000-0000-0000-000000000001']);
        $progress = ['remaining_distance_m' => 80000.0]; // 80km at 80km/h = 3600 seconds
        $result = $etaService->calculateEta($shipment, null, $progress);

        $this->assertIsArray($result);
        $this->assertEquals(3600.0, $result['baseline_remaining_seconds']);
    }
}
