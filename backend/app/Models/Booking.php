<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    public const UPDATED_AT = null;

    public const PENDING_PAYMENT = 'pending_payment';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED_BY_CUSTOMER = 'cancelled_by_customer';

    public const CANCELLED_BY_PRO = 'cancelled_by_pro';

    public const COMPLETED = 'completed';

    public const NO_SHOW = 'no_show';

    public const EXPIRED = 'expired';

    /** Statuts qui occupent le créneau (voir contrainte bookings_no_overlap). */
    public const ACTIVE_STATUSES = [self::PENDING_PAYMENT, self::CONFIRMED];

    protected $fillable = [
        'professional_id', 'service_id', 'customer_id', 'starts_at', 'ends_at',
        'blocked_until', 'status', 'hold_expires_at', 'cancel_token', 'source',
    ];

    protected $hidden = ['cancel_token'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'blocked_until' => 'datetime',
            'hold_expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
