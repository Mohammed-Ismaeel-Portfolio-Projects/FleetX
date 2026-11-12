<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only Shipment Order lifecycle history (C2.9-A3.2).
 *
 * Rows are created exclusively via TransitionShipmentOrderStatusAction.
 */
class ShipmentOrderStatusHistory extends Model
{
    protected $table = 'shipment_order_status_history';

    protected $fillable = [
        'shipment_order_id',
        'from_status_id',
        'to_status_id',
        'changed_by_user_id',
        'reason',
    ];

    public function shipmentOrder(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrder::class);
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
