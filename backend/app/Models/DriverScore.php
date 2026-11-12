<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssignmentEvaluationConfidence;
use App\Enums\DriverPerformanceMaturity;
use App\Enums\DriverPerformanceTrend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authoritative driver performance projection (DRIVER-EVAL-05).
 * Aggregated from finalized AssignmentEvaluation rows — not a second scoring engine.
 */
class DriverScore extends Model
{
    /** @use HasFactory<\Database\Factories\DriverScoreFactory> */
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'score',
        'score_label',
        'confidence',
        'maturity',
        'trend',
        'recent_score',
        'previous_score',
        'trend_delta',
        'total_deliveries',
        'late_deliveries',
        'evaluated_assignments_count',
        'incomplete_assignments_count',
        'category_scores',
        'aggregation_version',
        'aggregation_metadata',
        'last_evaluated_at',
        'aggregated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'recent_score' => 'decimal:2',
            'previous_score' => 'decimal:2',
            'trend_delta' => 'decimal:2',
            'total_deliveries' => 'integer',
            'late_deliveries' => 'integer',
            'evaluated_assignments_count' => 'integer',
            'incomplete_assignments_count' => 'integer',
            'confidence' => AssignmentEvaluationConfidence::class,
            'maturity' => DriverPerformanceMaturity::class,
            'trend' => DriverPerformanceTrend::class,
            'category_scores' => 'array',
            'aggregation_metadata' => 'array',
            'last_evaluated_at' => 'datetime',
            'aggregated_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function isScoreAvailable(): bool
    {
        return $this->score !== null
            && $this->maturity !== DriverPerformanceMaturity::UNAVAILABLE;
    }
}
