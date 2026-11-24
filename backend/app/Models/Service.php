<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name', 'duration_min', 'price_xof', 'deposit_xof', 'buffer_min', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_min' => 'integer',
            'price_xof' => 'integer',
            'deposit_xof' => 'integer',
            'buffer_min' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
