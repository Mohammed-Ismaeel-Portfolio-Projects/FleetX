<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of an AssignmentEvaluation (DRIVER-EVAL-02).
 *
 * Incomplete ≠ negative score. Missing telemetry must not become a penalty.
 */
enum AssignmentEvaluationStatus: string
{
    /** Created; evidence not yet processed. */
    case PENDING = 'pending';

    /** Evidence processing in progress. */
    case PROCESSING = 'processing';

    /** Finalized with sufficient attributable evidence. */
    case COMPLETED = 'completed';

    /** Finalized but evidence was missing/partial/ambiguous — not a scored failure. */
    case INCOMPLETE = 'incomplete';

    /** Superseded or explicitly invalidated; retained for audit. */
    case INVALIDATED = 'invalidated';

    public function isFinalized(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::INCOMPLETE,
            self::INVALIDATED,
        ], true);
    }

    public function isMutable(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING], true);
    }
}
