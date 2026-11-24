<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['name', 'phone_whatsapp'];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
