<?php

namespace App\Models;

use App\Enums\CollaborationType;
use App\Traits\HasAudit;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentFactory> */
    use HasFactory, HasUuid, SoftDeletes, HasAudit;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'shipment_number',
        'customer_company_id',
        'executing_company_id',
        'collaboration_type',
        'shipment_order_id',
        'reference_number',
        'priority',
        'status_id',
        'baseline_eta_seconds',
        'baseline_distance_meters',
        'baseline_routing_provider',
        'baseline_calculated_at',
        'cargo_type',
        'cargo_description',
        'cargo_weight',
        'cargo_volume',
        'package_count',
        'hazardous_cargo',
        'temperature_controlled',
        'pickup_company',
        'pickup_contact',
        'pickup_phone',
        'pickup_address',
        'pickup_city',
        'pickup_country',
        'delivery_company',
        'delivery_contact',
        'delivery_phone',
        'delivery_address',
        'delivery_city',
        'delivery_country',
        'planned_pickup_date',
        'planned_delivery_date',
        'actual_pickup_date',
        'actual_delivery_date',
        'actual_pickup_at',
        'actual_delivery_at',
        'special_instructions',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cargo_weight' => 'decimal:2',
            'cargo_volume' => 'decimal:2',
            'package_count' => 'integer',
            'hazardous_cargo' => 'boolean',
            'temperature_controlled' => 'boolean',
            'collaboration_type' => CollaborationType::class,
            'planned_pickup_date' => 'date',
            'planned_delivery_date' => 'date',
            'actual_pickup_date' => 'date',
            'actual_delivery_date' => 'date',
            'baseline_eta_seconds' => 'integer',
            'baseline_distance_meters' => 'integer',
            'baseline_calculated_at' => 'datetime',
            'actual_pickup_at' => 'datetime',
            'actual_delivery_at' => 'datetime',
        ];
    }

    public function customerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_company_id');
    }

    public function executingCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'executing_company_id');
    }

    public function isPartnerExecuted(): bool
    {
        if ($this->collaboration_type === CollaborationType::OUTBOUND_PARTNER) {
            return true;
        }

        if ($this->executing_company_id !== null) {
            return $this->executingCompany?->isPartnerCarrier() ?? true;
        }

        return false;
    }

    public function isInternallyExecuted(): bool
    {
        return !$this->isPartnerExecuted();
    }

    public function shipmentOrder(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrder::class, 'shipment_order_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestStatusHistory(): HasOne
    {
        return $this->hasOne(ShipmentStatusHistory::class)->latestOfMany();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function route(): HasOne
    {
        return $this->hasOne(Route::class);
    }

    public function trackingLogs(): HasMany
    {
        return $this->hasMany(TrackingLog::class)->orderByDesc('recorded_at');
    }

    /**
     * Active assignment for this shipment (operational commitment).
     */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->where('is_active', true);
    }

    /**
     * Most recent historical assignment for this shipment.
     */
    public function latestAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->latestOfMany();
    }

    public function deliveryProof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class);
    }

    public function operationalActuals(): HasOne
    {
        return $this->hasOne(ShipmentOperationalActuals::class);
    }

    public function finalPriceBreakdown(): HasOne
    {
        return $this->hasOne(ShipmentFinalPriceBreakdown::class);
    }

    public function invoiceLineItem(): HasOne
    {
        return $this->hasOne(InvoiceLineItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForCustomer($query, $companyId)
    {
        return $query->where('customer_company_id', $companyId);
    }

    public function scopeForStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    public function scopeForPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeForCountry($query, $country)
    {
        return $query->where('pickup_country', $country)
                    ->orWhere('delivery_country', $country);
    }

    public function isCompleted(): bool
    {
        return $this->status_id === Status::where('name', 'Completed')->where('type', 'shipment')->first()?->id;
    }

    public function isCancelled(): bool
    {
        return $this->status_id === Status::where('name', 'Cancelled')->where('type', 'shipment')->first()?->id;
    }

    public function isInTransit(): bool
    {
        return $this->status_id === Status::where('name', 'In Transit')->where('type', 'shipment')->first()?->id;
    }
}
