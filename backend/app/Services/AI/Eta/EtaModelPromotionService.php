<?php

namespace App\Services\AI\Eta;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EtaModelPromotionService
{
    private string $modelsDir;
    private string $evalDir;

    public function __construct(?string $modelsDir = null, ?string $evalDir = null)
    {
        $this->modelsDir = $modelsDir ?? base_path('ai/eta/models');
        $this->evalDir = $evalDir ?? base_path('ai/eta/evaluation');
    }

    public function validateReport(?string $reportPath = null): array
    {
        $path = $reportPath ?? $this->evalDir . '/phase_6_4_controlled_validation.json';

        if (!File::exists($path)) {
            throw new RuntimeException("Controlled validation report not found at: {$path}");
        }

        $report = json_decode(File::get($path), true);
        if (!$report || !isset($report['final_decision'])) {
            throw new RuntimeException("Invalid report structure in: {$path}");
        }

        $isPromotable = ($report['final_decision'] === 'PROMOTE');
        $allGatesPassed = true;

        if (isset($report['promotion_gates']) && is_array($report['promotion_gates'])) {
            foreach ($report['promotion_gates'] as $gate) {
                if (($gate['status'] ?? '') !== 'PASS') {
                    $allGatesPassed = false;
                    break;
                }
            }
        }

        return [
            'is_promotable' => $isPromotable && $allGatesPassed,
            'final_decision' => $report['final_decision'],
            'validated_at' => $report['validated_at'] ?? null,
            'all_gates_passed' => $allGatesPassed,
            'report_data' => $report,
        ];
    }

    public function promoteCandidate(bool $executeDeployment = false): array
    {
        $validation = $this->validateReport();

        if (!$validation['is_promotable']) {
            throw new RuntimeException("Refusing promotion: Validation decision is {$validation['final_decision']} and not all gates passed.");
        }

        $prodPath = $this->modelsDir . '/eta_residual_model.joblib';
        $candPath = $this->modelsDir . '/eta_residual_model_candidate_v2.joblib';

        if (!File::exists($candPath)) {
            $candPath = $this->modelsDir . '/eta_residual_model_candidate.joblib';
        }

        if (!File::exists($candPath)) {
            throw new RuntimeException("Candidate artifact not found at: {$candPath}");
        }

        if (!$executeDeployment) {
            return [
                'status' => 'READY_FOR_PROMOTION',
                'executed' => false,
                'message' => 'Controlled validation passed with decision PROMOTE. Execution withheld per Phase 6.4 safety policy.',
                'candidate_path' => $candPath,
                'production_path' => $prodPath,
            ];
        }

        // Perform Backup & Atomic Copy
        $backupPath = $this->modelsDir . '/eta_residual_model.joblib.bak.' . date('Ymd_His');
        if (File::exists($prodPath)) {
            File::copy($prodPath, $backupPath);
        }

        File::copy($candPath, $prodPath);

        Log::info("AI ETA Model Promoted Successfully", [
            'candidate_path' => $candPath,
            'production_path' => $prodPath,
            'backup_path' => $backupPath,
            'timestamp' => date('c')
        ]);

        return [
            'status' => 'PROMOTED',
            'executed' => true,
            'backup_path' => $backupPath,
            'production_path' => $prodPath,
            'promoted_at' => date('c')
        ];
    }
}
