<?php

namespace App\Services\Eta;

use App\Models\Shipment;
use App\Services\AI\Eta\EtaResidualInferenceService;
use App\Support\AiEtaStatus;
use Illuminate\Support\Facades\Config;

/**
 * FLeetX ETA Service
 * 
 * Calculates the authoritative baseline ETA based on routing engine estimates
 * and real-time tracking progress distance / assumed cruise speed, enhanced by
 * the Phase 5 hardened ML AI Residual Correction Service.
 */
class FLeetXEnhancedETAService
{
    public function __construct(
        private readonly ?EtaResidualInferenceService $inferenceService = null
    ) {}

    /**
     * Calculates the ETA payload for ops read model.
     *
     * @param Shipment $shipment
     * @param array<string, mixed>|null $journey
     * @param array<string, mixed>|null $progress
     * @param mixed $currentTracking
     * @return array<string, mixed>|null
     */
    public function calculateEta(
        Shipment $shipment,
        ?array $journey,
        ?array $progress,
        mixed $currentTracking = null
    ): ?array {
        $totalEstimate = isset($journey['estimated_travel_seconds'])
            ? (float) $journey['estimated_travel_seconds']
            : null;

        $baselineRemainingSeconds = null;
        if ($progress !== null && isset($progress['remaining_distance_m'])) {
            $cruise = (float) Config::get('simulation.rest.assumed_cruise_speed_kmh', 80.0);
            if ($cruise > 0) {
                $baselineRemainingSeconds = ((float) $progress['remaining_distance_m'] / 1000.0) / $cruise * 3600.0;
            }
        }

        if ($totalEstimate === null && $baselineRemainingSeconds === null) {
            return null;
        }

        // Apply AI Residual Correction if baseline remaining distance is available
        $aiService = $this->inferenceService ?? app(EtaResidualInferenceService::class);
        $inference = ($baselineRemainingSeconds !== null)
            ? $aiService->predictResidualEta($shipment, $baselineRemainingSeconds, $progress, $currentTracking)
            : [
                'status' => AiEtaStatus::AI_UNAVAILABLE,
                'source' => AiEtaStatus::SOURCE_ENGINE_JOURNEY,
                'predicted_residual_seconds' => 0.0,
                'ai_remaining_seconds' => $baselineRemainingSeconds,
                'model_version' => null,
                'inference_duration_ms' => 0.0,
            ];

        $finalRemainingSeconds = $inference['ai_remaining_seconds'] ?? $baselineRemainingSeconds;

        return [
            'estimated_total_travel_seconds' => $totalEstimate,
            'estimated_remaining_seconds' => $finalRemainingSeconds,
            'baseline_remaining_seconds' => $baselineRemainingSeconds,
            'predicted_residual_seconds' => $inference['predicted_residual_seconds'] ?? 0.0,
            'ai_remaining_seconds' => $finalRemainingSeconds,
            'source' => $inference['source'] ?? AiEtaStatus::SOURCE_ENGINE_JOURNEY,
            'ai_status' => $inference['status'] ?? AiEtaStatus::AI_UNAVAILABLE,
            'ai_model_version' => $inference['model_version'] ?? null,
            'inference_duration_ms' => $inference['inference_duration_ms'] ?? 0.0,
        ];
    }
}
