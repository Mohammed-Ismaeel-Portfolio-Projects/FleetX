<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;
    use HasUuid;

    public const TYPE_DISPATCHER_DRIVER = 'dispatcher_driver';
    public const TYPE_DISPATCHER_CUSTOMER = 'dispatcher_customer';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'type',
        'shipment_id',
        'assignment_id',
        'status',
        'created_by',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_DISPATCHER_DRIVER,
            self::TYPE_DISPATCHER_CUSTOMER,
        ];
    }
}
