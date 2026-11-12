<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only Shipment operational status history (C2.9-C1.8).
 *
 * Rows are created exclusively via TransitionShipmentStatusAction.
 */
class ShipmentStatusHistory extends Model
{
    protected $table = 'shipment_status_history';

    protected $fillable = [
        'shipment_id',
        'from_status_id',
        'to_status_id',
        'changed_by_user_id',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
