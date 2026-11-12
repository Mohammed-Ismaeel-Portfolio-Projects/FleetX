<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Classification of telemetry/evidence provenance (DRIVER-EVAL-02).
 *
 * Convention aligned with live safety ingest: received_at present ⇒ LIVE;
 * received_at absent ⇒ SIMULATION (non-live). UNKNOWN is for explicit ambiguity.
 */
enum DriverEvaluationEvidenceSource: string
{
    case LIVE = 'live';
    case SIMULATION = 'simulation';
    case UNKNOWN = 'unknown';
}
