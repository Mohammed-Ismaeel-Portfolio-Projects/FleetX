<?php

namespace Tests\Unit\AI;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class EtaValidationReportTest extends TestCase
{
    private string $aiBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiBaseDir = base_path('ai/eta');
    }

    public function test_automated_validation_script_executes_and_generates_evidence_artifacts(): void
    {
        $scriptPath = $this->aiBaseDir . '/evaluation/generate_validation_report.py';
        $this->assertFileExists($scriptPath);

        $command = "py -3.11 ai/eta/evaluation/generate_validation_report.py";
        $process = Process::path(base_path())->run($command);

        $this->assertTrue(
            $process->successful(),
            'Validation report script execution failed: ' . $process->errorOutput()
        );

        $reportJsonPath = $this->aiBaseDir . '/evaluation/validation_report.json';
        $reportCsvPath = $this->aiBaseDir . '/evaluation/validation_report.csv';
        $reportMdPath = $this->aiBaseDir . '/evaluation/validation_summary.md';

        $this->assertFileExists($reportJsonPath);
        $this->assertFileExists($reportCsvPath);
        $this->assertFileExists($reportMdPath);
    }

    public function test_validation_report_json_structure_and_mathematical_consistency(): void
    {
        $reportJsonPath = $this->aiBaseDir . '/evaluation/validation_report.json';
        $this->assertFileExists($reportJsonPath);

        $report = json_decode(file_get_contents($reportJsonPath), true);
        $this->assertIsArray($report);

        $this->assertArrayHasKey('validation_status', $report);
        $this->assertContains($report['validation_status'], ['PASS', 'WARNING']);

        $metrics = $report['recalculated_metrics'];
        $this->assertArrayHasKey('baseline_mae_seconds', $metrics);
        $this->assertArrayHasKey('ai_mae_seconds', $metrics);
        $this->assertArrayHasKey('mae_improvement_percentage', $metrics);

        $baseMae = $metrics['baseline_mae_seconds'];
        $aiMae = $metrics['ai_mae_seconds'];
        $maeImpPct = $metrics['mae_improvement_percentage'];

        $this->assertGreaterThan(0, $baseMae);
        $this->assertGreaterThan(0, $aiMae);

        // Verify mathematical contract: MAE Improvement % = ((baseline - ai) / baseline) * 100
        $expectedPct = round((($baseMae - $aiMae) / $baseMae) * 100.0, 2);
        $this->assertEquals($expectedPct, $maeImpPct);
    }

    public function test_progress_buckets_and_leakage_audit_in_validation_report(): void
    {
        $reportJsonPath = $this->aiBaseDir . '/evaluation/validation_report.json';
        $this->assertFileExists($reportJsonPath);

        $report = json_decode(file_get_contents($reportJsonPath), true);
        $this->assertIsArray($report);

        // Progress Buckets
        $buckets = $report['progress_bucket_analysis'];
        $expectedBuckets = ['0-20%', '20-40%', '40-60%', '60-80%', '80-100%'];
        foreach ($expectedBuckets as $bLabel) {
            $this->assertArrayHasKey($bLabel, $buckets);
            $this->assertGreaterThan(0, $buckets[$bLabel]['sample_count']);
            $this->assertGreaterThan(0, $buckets[$bLabel]['baseline_mae_seconds']);
            $this->assertGreaterThan(0, $buckets[$bLabel]['ai_mae_seconds']);
        }

        // Leakage Audit
        $leakageAudit = $report['leakage_audit'];
        $this->assertEquals('PASS', $leakageAudit['status']);
        $this->assertEmpty($leakageAudit['leakage_features_detected']);
    }
}
