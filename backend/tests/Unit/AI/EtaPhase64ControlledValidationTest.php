<?php

namespace Tests\Unit\AI;

use App\Services\AI\Eta\EtaModelPromotionService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class EtaPhase64ControlledValidationTest extends TestCase
{
    private string $aiBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiBaseDir = base_path('ai/eta');
    }

    public function test_phase_6_4_controlled_validation_script_executes_and_generates_all_artifacts(): void
    {
        $scriptPath = $this->aiBaseDir . '/evaluation/run_phase_6_4_controlled_validation.py';
        $this->assertFileExists($scriptPath);

        $command = "py -3.11 ai/eta/evaluation/run_phase_6_4_controlled_validation.py";
        $process = Process::path(base_path())->run($command);

        $this->assertTrue(
            $process->successful(),
            'Phase 6.4 controlled validation script execution failed: ' . $process->errorOutput()
        );

        $valJsonPath = $this->aiBaseDir . '/evaluation/phase_6_4_controlled_validation.json';
        $valCsvPath = $this->aiBaseDir . '/evaluation/phase_6_4_controlled_validation.csv';
        $summaryMdPath = $this->aiBaseDir . '/evaluation/phase_6_4_summary.md';

        $this->assertFileExists($valJsonPath);
        $this->assertFileExists($valCsvPath);
        $this->assertFileExists($summaryMdPath);

        $plotsDir = $this->aiBaseDir . '/evaluation/plots';
        $this->assertFileExists($plotsDir . '/phase_6_4_model_error_comparison.png');
        $this->assertFileExists($plotsDir . '/phase_6_4_shipment_stability.png');
        $this->assertFileExists($plotsDir . '/phase_6_4_bootstrap_mae.png');
    }

    public function test_promotion_gates_evaluation_and_production_protection(): void
    {
        $valJsonPath = $this->aiBaseDir . '/evaluation/phase_6_4_controlled_validation.json';
        $this->assertFileExists($valJsonPath);

        $report = json_decode(file_get_contents($valJsonPath), true);
        $this->assertIsArray($report);

        $this->assertEquals('PROMOTE', $report['final_decision']);
        $this->assertTrue($report['checksums']['production_checksum_unchanged']);

        $gates = $report['promotion_gates'];
        $this->assertCount(10, $gates);

        foreach ($gates as $gate) {
            $this->assertEquals(
                'PASS',
                $gate['status'],
                "Mandatory promotion gate failed: {$gate['gate']}"
            );
        }

        // Verify Bootstrap CI Excludes Zero
        $bootCi = $report['bootstrap_confidence_analysis']['candidate_v2_vs_production']['mae_improvement_ci'];
        $this->assertGreaterThan(0, $bootCi[0]);
    }

    public function test_eta_model_promotion_service_readiness_and_safety_checks(): void
    {
        $service = new EtaModelPromotionService();

        $validation = $service->validateReport();
        $this->assertTrue($validation['is_promotable']);
        $this->assertEquals('PROMOTE', $validation['final_decision']);

        $readiness = $service->promoteCandidate(false);
        $this->assertEquals('READY_FOR_PROMOTION', $readiness['status']);
        $this->assertFalse($readiness['executed']);
    }
}
