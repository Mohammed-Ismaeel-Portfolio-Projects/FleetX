<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssignmentEvaluationEvidenceQuality;
use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentEvaluation extends Model
{
    /** @use HasFactory<\Database\Factories\AssignmentEvaluationFactory> */
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'shipment_id',
        'driver_id',
        'vehicle_id',
        'evaluation_mode',
        'status',
        'evidence_quality',
        'overall_score',
        'score_label',
        'confidence',
        'scoring_version',
        'category_scores',
        'scoring_metadata',
        'revision',
        'supersedes_evaluation_id',
        'started_at',
        'completed_at',
        'finalized_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_mode' => AssignmentEvaluationMode::class,
            'status' => AssignmentEvaluationStatus::class,
            'evidence_quality' => AssignmentEvaluationEvidenceQuality::class,
            'confidence' => \App\Enums\AssignmentEvaluationConfidence::class,
            'overall_score' => 'decimal:2',
            'revision' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'finalized_at' => 'datetime',
            'category_scores' => 'array',
            'scoring_metadata' => 'array',
            'metadata' => 'array',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_evaluation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssignmentEvaluationEvent::class);
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null
            || ($this->status?->isFinalized() ?? false);
    }

    public function isMutable(): bool
    {
        return ! $this->isFinalized() && ($this->status?->isMutable() ?? false);
    }
}
