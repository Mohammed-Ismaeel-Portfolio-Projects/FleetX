<?php

declare(strict_types=1);

namespace App\Enums;

enum DriverEvaluationScoreLabel: string
{
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case ACCEPTABLE = 'acceptable';
    case NEEDS_IMPROVEMENT = 'needs_improvement';
    case POOR = 'poor';

    public static function fromScore(float $score): self
    {
        $bands = config('driver_evaluation.scoring.labels', []);

        if ($score >= (float) ($bands['excellent'] ?? 90)) {
            return self::EXCELLENT;
        }
        if ($score >= (float) ($bands['good'] ?? 80)) {
            return self::GOOD;
        }
        if ($score >= (float) ($bands['acceptable'] ?? 70)) {
            return self::ACCEPTABLE;
        }
        if ($score >= (float) ($bands['needs_improvement'] ?? 60)) {
            return self::NEEDS_IMPROVEMENT;
        }

        return self::POOR;
    }
}
