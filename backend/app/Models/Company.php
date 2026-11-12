<?php

namespace App\Models;

use App\Enums\CompanyType;
use App\Enums\CustomerType;
use App\Traits\HasAudit;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Company Model
 * 
 * Represents a B2B client company that requests transportation services from FleetX.
 * 
 * This model is intentionally lightweight - it contains only Eloquent configuration
 * and relationship definitions. All business logic, validation, and calculations belong
 * in Actions and Services.
 * 
 * @property string $id UUID primary key
 * @property string $company_name Display name for UI and communications
 * @property string $legal_name Official legal name for contracts and invoices
 * @property string $company_code Unique human-readable identifier
 * @property string|null $tax_number Tax identification number
 * @property string|null $vat_number VAT registration number
 * @property string|null $business_registration_number Official business registration number
 * @property int|null $industry_id Industry classification reference
 * @property int|null $company_size_id Company size reference
 * @property string $primary_email Main contact email
 * @property string|null $primary_phone Main contact phone

 * @property string|null $website Company website
 * @property string $country ISO 3166-1 alpha-2 country code
 * @property string|null $state_province State or province name
 * @property string|null $district District or region
 * @property string $city City name
 * @property string|null $building_number Building number
 * @property string $address_line_1 Primary street address
 * @property string|null $address_line_2 Secondary address
 * @property string $postal_code Postal/ZIP code
 * @property float|null $latitude Geographic latitude
 * @property float|null $longitude Geographic longitude
 * @property string|null $timezone Company timezone
 * @property string $currency_code Default currency ISO 4217
 * @property string|null $language_code Preferred language ISO 639-1
 * @property int $status_id Status reference to statuses table
 * @property bool $is_active Quick active/inactive flag
 * @property string|null $notes Internal notes
 * @property string|null $logo_path File path to company logo
 * @property string|null $created_by User who created the record
 * @property string|null $updated_by User who last updated the record
 * @property \Illuminate\Support\Carbon $created_at Record creation timestamp
 * @property \Illuminate\Support\Carbon $updated_at Record last update timestamp
 * @property \Illuminate\Support\Carbon|null $deleted_at Soft delete timestamp
 */
class Company extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;
    use HasAudit;

    /**
     * The table associated with the model.
     */
    protected $table = 'companies';

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     * 
     * All fields that can be set via mass assignment (create, fill, update).
     * Excludes auto-managed fields: id, created_at, updated_at, deleted_at.
     */
    protected $fillable = [
        'company_name',
        'legal_name',
        'company_code',
        'tax_number',
        'vat_number',
        'business_registration_number',
        'industry_id',
        'company_size_id',
        'primary_email',
        'primary_phone',
        'website',
        'country',
        'state_province',
        'district',
        'city',
        'building_number',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'latitude',
        'longitude',
        'timezone',
        'currency_code',
        'customer_type',
        'company_type',
        'language_code',
        'status_id',
        'is_active',
        'notes',
        'logo_path',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     * 
     * Automatic type casting for Eloquent attributes.
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_active' => 'boolean',
            'customer_type' => CustomerType::class,
            'company_type' => CompanyType::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function isInternal(): bool
    {
        return $this->company_type === CompanyType::INTERNAL;
    }

    public function isClient(): bool
    {
        return $this->company_type === CompanyType::CLIENT || $this->company_type === CompanyType::HYBRID;
    }

    public function isPartnerCarrier(): bool
    {
        return $this->company_type === CompanyType::PARTNER_CARRIER || $this->company_type === CompanyType::HYBRID;
    }

    public function isHybrid(): bool
    {
        return $this->company_type === CompanyType::HYBRID;
    }

    /**
     * Client portal users linked to this company (users.company_id).
     *
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    /**
     * Shipments executed by this company as the operational carrier.
     */
    public function executedShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'executing_company_id');
    }

    /**
     * Vehicles owned or operated by this company.
     */
    public function ownedVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'company_id');
    }

    /**
     * Drivers employed or contracted by this company.
     */
    public function employedDrivers(): HasMany
    {
        return $this->hasMany(Driver::class, 'company_id');
    }

    /**
     * Trailers owned or operated by this company.
     */
    public function ownedTrailers(): HasMany
    {
        return $this->hasMany(Trailer::class, 'company_id');
    }

    /**
     * Relationship: Company has many Contacts.
     * 
     * TODO: Create Contact model
     * 
     * @return HasMany<Contact>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Relationship: Company has many Orders.
     * 
     * @return HasMany<Order>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Shipment requests (customer intent) scoped to this company.
     */
    public function shipmentOrders(): HasMany
    {
        return $this->hasMany(ShipmentOrder::class, 'customer_company_id');
    }

    /**
     * Legacy per-shipment invoice records (pre-monthly billing architecture).
     *
     * @return HasMany<Invoice>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Monthly billing invoices (Phase 6a+).
     *
     * @return HasMany<MonthlyInvoice>
     */
    public function monthlyInvoices(): HasMany
    {
        return $this->hasMany(MonthlyInvoice::class);
    }

    /**
     * Relationship: Company has many Documents.
     * 
     * TODO: Create Document model (commercial documents)
     * 
     * @return HasMany<Document>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Relationship: Company has many Branches.
     * 
     * TODO: Create Branch model
     * 
     * @return HasMany<Branch>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Relationship: Company belongs to a Status.
     * 
     * @return BelongsTo<Status, self>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Relationship: Company belongs to a Creator (User).
     * 
     * @return BelongsTo<User, self>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Company belongs to an Updater (User).
     * 
     * @return BelongsTo<User, self>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship: Company belongs to an Industry.
     * 
     * TODO: Create Industry model
     * 
     * @return BelongsTo<Industry, self>
     */
    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    /**
     * Relationship: Company belongs to a CompanySize.
     * 
     * TODO: Create CompanySize model
     * 
     * @return BelongsTo<CompanySize, self>
     */
    public function companySize(): BelongsTo
    {
        return $this->belongsTo(CompanySize::class);
    }
}
