<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\DTO;

use App\Models\Assignment;

/**
 * Result of deterministic TrackingLog → Assignment attribution (DRIVER-EVAL-02).
 */
final class TelemetryAttributionResult
{
    public const STATUS_ATTRIBUTED = 'attributed';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_AMBIGUOUS = 'ambiguous';

    public const STATUS_INSUFFICIENT = 'insufficient';

    private function __construct(
        public readonly string $status,
        public readonly ?Assignment $assignment = null,
        public readonly ?string $reason = null,
        public readonly int $matchCount = 0,
    ) {}

    public static function attributed(Assignment $assignment): self
    {
        return new self(self::STATUS_ATTRIBUTED, $assignment, null, 1);
    }

    public static function unmatched(string $reason): self
    {
        return new self(self::STATUS_UNMATCHED, null, $reason, 0);
    }

    public static function ambiguous(string $reason, int $matchCount): self
    {
        return new self(self::STATUS_AMBIGUOUS, null, $reason, $matchCount);
    }

    public static function insufficient(string $reason): self
    {
        return new self(self::STATUS_INSUFFICIENT, null, $reason, 0);
    }

    public function isAttributed(): bool
    {
        return $this->status === self::STATUS_ATTRIBUTED && $this->assignment !== null;
    }
}
