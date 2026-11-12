<?php

namespace App\Models;

use App\Support\ShipmentOrderStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShipmentOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentOrderFactory> */
    use HasFactory, HasUuid;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Tenant boundary: customer_company_id is the company scope for client access.
     * Security-sensitive fields (status_id, converted_shipment_id, submission_revision)
     * must only be set by server-side actions — not client mass assignment in API layers.
     *
     * Lifecycle status_id changes must use TransitionShipmentOrderStatusAction (C2.9-A3.2).
     */
    protected $fillable = [
        'request_number',
        'customer_company_id',
        'submitted_by_user_id',
        'status_id',
        'submission_revision',
        'payload_json',
        'reference_number',
        'priority',
        'cargo_type',
        'trailer_type',
        'cargo_description',
        'cargo_weight',
        'cargo_volume',
        'package_count',
        'hazardous_cargo',
        'temperature_controlled',
        'selected_service_ids',
        'border_crossing',
        'insurance_required',
        'pickup_company',
        'pickup_contact',
        'pickup_phone',
        'pickup_address',
        'pickup_city',
        'pickup_country',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_company',
        'delivery_contact',
        'delivery_phone',
        'delivery_address',
        'delivery_city',
        'delivery_country',
        'delivery_latitude',
        'delivery_longitude',
        'requested_pickup_date',
        'requested_delivery_date',
        'special_instructions',
        'client_notes',
        'dispatcher_notes',
        'converted_shipment_id',
        'planned_distance_meters',
        'planned_duration_seconds',
        'planned_route_geometry',
        'planned_route_origin_latitude',
        'planned_route_origin_longitude',
        'planned_route_destination_latitude',
        'planned_route_destination_longitude',
        'planned_route_status',
        'planned_route_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_revision' => 'integer',
            'payload_json' => 'array',
            'cargo_weight' => 'decimal:2',
            'cargo_volume' => 'decimal:2',
            'package_count' => 'integer',
            'hazardous_cargo' => 'boolean',
            'temperature_controlled' => 'boolean',
            'selected_service_ids' => 'array',
            'border_crossing' => 'boolean',
            'insurance_required' => 'boolean',
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'delivery_latitude' => 'decimal:7',
            'delivery_longitude' => 'decimal:7',
            'requested_pickup_date' => 'date',
            'requested_delivery_date' => 'date',
            'planned_distance_meters' => 'integer',
            'planned_duration_seconds' => 'integer',
            'planned_route_geometry' => 'array',
            'planned_route_origin_latitude' => 'decimal:7',
            'planned_route_origin_longitude' => 'decimal:7',
            'planned_route_destination_latitude' => 'decimal:7',
            'planned_route_destination_longitude' => 'decimal:7',
            'planned_route_calculated_at' => 'datetime',
        ];
    }

    public function customerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_company_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function convertedShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'converted_shipment_id');
    }

    /**
     * Shipment created from this order (reverse traceability via shipments.shipment_order_id).
     */
    public function linkedShipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'shipment_order_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ShipmentOrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestStatusHistory(): HasOne
    {
        return $this->hasOne(ShipmentOrderStatusHistory::class)->latestOfMany();
    }

    /**
     * Company tenancy scope — server-derived company id only.
     */
    public function scopeForCustomer($query, string $companyId)
    {
        return $query->where('customer_company_id', $companyId);
    }

    public function scopeForStatus($query, int $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    public function priceBreakdown(): HasOne
    {
        return $this->hasOne(ShipmentOrderPriceBreakdown::class);
    }

    public function scopeWithStatusName($query, string $statusName)
    {
        return $query->whereHas('status', function ($q) use ($statusName) {
            $q->where('name', $statusName)->where('type', ShipmentOrderStatus::TYPE);
        });
    }

    public function isConverted(): bool
    {
        return $this->converted_shipment_id !== null
            && $this->status?->type === ShipmentOrderStatus::TYPE
            && $this->status?->name === ShipmentOrderStatus::CONVERTED;
    }

    public function trackingSession(): HasOne
    {
        return $this->hasOne(TrackingSession::class);
    }
}
