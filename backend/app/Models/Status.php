<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    /** @use HasFactory<\Database\Factories\StatusFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
    ];

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function trucks(): HasMany
    {
        return $this->hasMany(Truck::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shipmentOrders(): HasMany
    {
        return $this->hasMany(ShipmentOrder::class, 'customer_company_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
}
