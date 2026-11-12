<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DriverEvaluationEventCategory;
use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEventSeverity;
use App\Enums\DriverEvaluationEvidenceSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentEvaluationEvent extends Model
{
    /** @use HasFactory<\Database\Factories\AssignmentEvaluationEventFactory> */
    use HasFactory;

    protected $fillable = [
        'assignment_evaluation_id',
        'assignment_id',
        'shipment_id',
        'driver_id',
        'vehicle_id',
        'event_code',
        'category',
        'severity',
        'occurred_at',
        'window_start_at',
        'window_end_at',
        'evidence_source',
        'detector_code',
        'detector_version',
        'idempotency_key',
        'evidence_tracking_log_ids',
        'evidence_metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_code' => DriverEvaluationEventCode::class,
            'category' => DriverEvaluationEventCategory::class,
            'severity' => DriverEvaluationEventSeverity::class,
            'evidence_source' => DriverEvaluationEvidenceSource::class,
            'occurred_at' => 'datetime',
            'window_start_at' => 'datetime',
            'window_end_at' => 'datetime',
            'evidence_tracking_log_ids' => 'array',
            'evidence_metadata' => 'array',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(AssignmentEvaluation::class, 'assignment_evaluation_id');
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
}
