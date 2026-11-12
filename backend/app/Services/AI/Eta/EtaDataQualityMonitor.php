<?php

namespace App\Services\AI\Eta;

use App\Support\AiEtaStatus;
use Illuminate\Support\Facades\Log;

class EtaDataQualityMonitor
{
    /**
     * Validate prediction-time features for anomalies and data quality issues.
     *
     * @param array<string, mixed> $features
     * @param array<string>|null $requiredFeatureNames
     * @return array{is_valid: bool, warnings: array<string>}
     */
    public function validateFeatures(array $features, ?array $requiredFeatureNames = null): array
    {
        $warnings = [];

        // 1. Check feature completeness against required manifest
        if ($requiredFeatureNames !== null) {
            foreach ($requiredFeatureNames as $col) {
                if (! array_key_exists($col, $features)) {
                    $warnings[] = "Missing required feature '{$col}'";
                }
            }
        }

        // 2. Validate numeric values for NaN / Inf
        foreach ($features as $key => $val) {
            if (is_float($val) && (is_nan($val) || is_infinite($val))) {
                $warnings[] = "Non-finite numeric value in feature '{$key}'";
            }
        }

        // 3. Geolocation bounds check
        $lat = (float) ($features['current_latitude'] ?? 0.0);
        $lng = (float) ($features['current_longitude'] ?? 0.0);
        if ($lat < -90.0 || $lat > 90.0) {
            $warnings[] = "Latitude out of bounds ({$lat})";
        }
        if ($lng < -180.0 || $lng > 180.0) {
            $warnings[] = "Longitude out of bounds ({$lng})";
        }

        // 4. Distance and Progress bounds check
        $remDist = (float) ($features['remaining_distance_meters'] ?? 0.0);
        if ($remDist < 0.0) {
            $warnings[] = "Negative remaining distance ({$remDist})";
        }

        $completionPct = (float) ($features['completion_percentage'] ?? 0.0);
        if ($completionPct < 0.0 || $completionPct > 100.0) {
            $warnings[] = "Completion percentage out of bounds [0, 100] ({$completionPct})";
        }

        // 5. Telemetry speed and heading check
        $speed = (float) ($features['current_speed_kmh'] ?? 0.0);
        if ($speed < 0.0 || $speed > 220.0) {
            $warnings[] = "Unreasonable telemetry speed ({$speed} km/h)";
        }

        $heading = (float) ($features['heading'] ?? 0.0);
        if ($heading < 0.0 || $heading > 360.0) {
            $warnings[] = "Heading out of bounds [0, 360] ({$heading})";
        }

        $isValid = count($warnings) === 0;

        if (! $isValid) {
            Log::warning('AI ETA Data Quality Warning', [
                'status' => AiEtaStatus::DATA_QUALITY_WARNING,
                'warnings' => $warnings,
            ]);
        }

        return [
            'is_valid' => $isValid,
            'warnings' => $warnings,
        ];
    }
}
