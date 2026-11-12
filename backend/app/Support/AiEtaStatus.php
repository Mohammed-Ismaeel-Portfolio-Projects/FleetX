<?php

namespace App\Support;

/**
 * Standardized AI ETA Inference Status Contracts.
 */
final class AiEtaStatus
{
    public const AI_AVAILABLE = 'AI_AVAILABLE';
    public const AI_UNAVAILABLE = 'AI_UNAVAILABLE';
    public const AI_INVALID = 'AI_INVALID';
    public const DATA_QUALITY_WARNING = 'DATA_QUALITY_WARNING';

    public const SOURCE_AI_RESIDUAL = 'ai_residual';
    public const SOURCE_BASELINE_ONLY = 'baseline_only';
    public const SOURCE_ENGINE_JOURNEY = 'engine_journey_and_progress';

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::AI_AVAILABLE,
            self::AI_UNAVAILABLE,
            self::AI_INVALID,
            self::DATA_QUALITY_WARNING,
        ];
    }
}
