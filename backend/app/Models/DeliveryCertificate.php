<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'delivery_proof_id',
        'generated_by',
        'generated_at',
        'file_path',
        'hash',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function deliveryProof(): BelongsTo
    {
        return $this->belongsTo(DeliveryProof::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
