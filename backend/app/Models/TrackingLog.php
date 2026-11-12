<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingLog extends Model
{
    /** @use HasFactory<\Database\Factories\TrackingLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'accuracy',
        'recorded_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed' => 'decimal:2',
            'heading' => 'decimal:2',
            'accuracy' => 'decimal:2',
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Get the assignment for this tracking log through the shipment.
     */
    public function assignment()
    {
        return $this->shipment()->first()?->activeAssignment();
    }
}
