<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const UPDATED_AT = null;

    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const REFUND_DUE = 'refund_due';

    public const REFUNDED = 'refunded';

    protected $fillable = [
        'booking_id', 'amount_xof', 'operator', 'provider', 'provider_ref',
        'status', 'raw_payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_xof' => 'integer',
            'raw_payload' => 'array',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
