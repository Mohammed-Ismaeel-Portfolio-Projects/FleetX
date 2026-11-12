<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryProof extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryProofFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'assignment_id',
        'driver_id',
        'receiver_name',
        'receiver_role',
        'image_url',
        'signature',
        'latitude',
        'longitude',
        'delivery_notes',
        'condition_status',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'confirmed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
