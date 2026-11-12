<?php

namespace App\Services\AI\Eta;

use App\Models\Shipment;
use App\Support\AiEtaStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class EtaResidualInferenceService
{
    public function __construct(
        private readonly EtaFeatureBuilder $featureBuilder,
        private readonly EtaModelArtifactLoader $artifactLoader,
        private readonly EtaDataQualityMonitor $dataQualityMonitor
    ) {}

    /**
     * Predict residual correction and compute final AI remaining ETA.
     * Guaranteed safe: always falls back to baseline remaining seconds on any error.
     *
     * @param Shipment $shipment
     * @param float $baselineRemainingSeconds
     * @param array<string, mixed>|null $progress
     * @param mixed $currentTracking
     * @return array{
     *     status: string,
     *     source: string,
     *     predicted_residual_seconds: float,
     *     ai_remaining_seconds: float,
     *     model_version: ?string,
     *     inference_duration_ms: float
     * }
     */
    public function predictResidualEta(
        Shipment $shipment,
        float $baselineRemainingSeconds,
        ?array $progress = null,
        mixed $currentTracking = null
    ): array {
        $startTime = microtime(true);
        $modelVersion = $this->artifactLoader->getModelVersion();

        $fallback = [
            'status' => AiEtaStatus::AI_UNAVAILABLE,
            'source' => AiEtaStatus::SOURCE_BASELINE_ONLY,
            'predicted_residual_seconds' => 0.0,
            'ai_remaining_seconds' => max(0.0, $baselineRemainingSeconds),
            'model_version' => $modelVersion,
            'inference_duration_ms' => 0.0,
        ];

        // Check if AI ETA feature flag is enabled
        if (! Config::get('ai.eta.enabled', true)) {
            Log::debug('EtaResidualInferenceService: AI ETA is disabled via config.');
            return $fallback;
        }

        if (! $this->artifactLoader->isArtifactReady()) {
            Log::debug('EtaResidualInferenceService: model artifacts not ready, using baseline.');
            return $fallback;
        }

        try {
            // Build prediction-time feature payload
            $features = $this->featureBuilder->buildFeatures($shipment, $progress, $currentTracking);

            // Data Quality Check
            $manifest = $this->artifactLoader->getManifest();
            $requiredNames = $manifest['feature_names'] ?? null;
            $this->dataQualityMonitor->validateFeatures($features, $requiredNames);

            // Execute Python inference script via temporary feature payload file
            $tempFile = storage_path('app/temp_eta_features_' . uniqid() . '.json');
            file_put_contents($tempFile, json_encode($features));

            $cleanPath = str_replace('\\', '/', $tempFile);
            $scriptPath = $this->artifactLoader->getScriptPath();
            $timeout = (int) Config::get('ai.eta.timeout_seconds', 5.0);

            $command = 'py -3.11 "' . str_replace('\\', '/', $scriptPath) . '" "' . $cleanPath . '"';
            $processResult = Process::path(base_path())
                ->timeout($timeout)
                ->run($command);

            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            $durationMs = round((microtime(true) - $startTime) * 1000.0, 2);

            if (! $processResult->successful()) {
                Log::warning('EtaResidualInferenceService: inference process failed', [
                    'shipment_id' => $shipment->id,
                    'command' => $command,
                    'exit_code' => $processResult->exitCode(),
                    'error' => $processResult->errorOutput(),
                    'output' => $processResult->output(),
                ]);
                $fallback['inference_duration_ms'] = $durationMs;
                return $fallback;
            }

            $outputJson = trim($processResult->output());
            $parsed = json_decode($outputJson, true);

            if (! is_array($parsed) || ($parsed['status'] ?? '') !== 'success' || ! isset($parsed['predicted_residual_seconds'])) {
                Log::warning('EtaResidualInferenceService: invalid inference output format', [
                    'shipment_id' => $shipment->id,
                    'raw_output' => $outputJson,
                    'duration_ms' => $durationMs,
                ]);
                return [
                    'status' => AiEtaStatus::AI_INVALID,
                    'source' => AiEtaStatus::SOURCE_BASELINE_ONLY,
                    'predicted_residual_seconds' => 0.0,
                    'ai_remaining_seconds' => max(0.0, $baselineRemainingSeconds),
                    'model_version' => $modelVersion,
                    'inference_duration_ms' => $durationMs,
                ];
            }

            $predictedResidual = (float) $parsed['predicted_residual_seconds'];

            // Sanity Validation: check for finite numeric value
            if (is_nan($predictedResidual) || is_infinite($predictedResidual)) {
                Log::warning('EtaResidualInferenceService: non-finite residual prediction', [
                    'shipment_id' => $shipment->id,
                    'residual' => $predictedResidual,
                    'duration_ms' => $durationMs,
                ]);
                return [
                    'status' => AiEtaStatus::AI_INVALID,
                    'source' => AiEtaStatus::SOURCE_BASELINE_ONLY,
                    'predicted_residual_seconds' => 0.0,
                    'ai_remaining_seconds' => max(0.0, $baselineRemainingSeconds),
                    'model_version' => $modelVersion,
                    'inference_duration_ms' => $durationMs,
                ];
            }

            // Statistically justified residual bounds
            $minBound = (float) Config::get('ai.eta.residual_min_bound', -43200.0);
            $maxBound = (float) Config::get('ai.eta.residual_max_bound', 43200.0);
            $clippedResidual = max($minBound, min($maxBound, $predictedResidual));

            // Final AI Remaining ETA Calculation: Baseline + Residual (clamped to >= 0)
            $aiRemainingSeconds = max(0.0, $baselineRemainingSeconds + $clippedResidual);

            // Observability Logging
            if (Config::get('ai.eta.logging_enabled', true)) {
                Log::info('AI ETA Inference Executed', [
                    'shipment_id' => $shipment->id,
                    'baseline_remaining_seconds' => $baselineRemainingSeconds,
                    'predicted_residual_seconds' => round($clippedResidual, 2),
                    'ai_remaining_seconds' => round($aiRemainingSeconds, 2),
                    'status' => AiEtaStatus::AI_AVAILABLE,
                    'source' => AiEtaStatus::SOURCE_AI_RESIDUAL,
                    'model_version' => $modelVersion,
                    'duration_ms' => $durationMs,
                ]);
            }

            return [
                'status' => AiEtaStatus::AI_AVAILABLE,
                'source' => AiEtaStatus::SOURCE_AI_RESIDUAL,
                'predicted_residual_seconds' => round($clippedResidual, 2),
                'ai_remaining_seconds' => round($aiRemainingSeconds, 2),
                'model_version' => $modelVersion,
                'inference_duration_ms' => $durationMs,
            ];

        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000.0, 2);
            Log::warning('EtaResidualInferenceService exception, falling back to baseline', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            $fallback['inference_duration_ms'] = $durationMs;
            return $fallback;
        }
    }
}
