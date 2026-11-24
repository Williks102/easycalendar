<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Professional extends Authenticatable
{
    use HasApiTokens;

    public const UPDATED_AT = null;

    protected $fillable = [
        'phone', 'name', 'slug', 'business_name', 'address', 'timezone',
        'whatsapp_number', 'payout_operator', 'payout_phone', 'status',
    ];

    /** Mêmes valeurs par défaut qu'en base, pour un modèle qui vient d'être créé. */
    protected $attributes = [
        'status' => 'active',
        'timezone' => 'Africa/Abidjan',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'created_at' => 'datetime',
        ];
    }

    /** Le mot de passe est stocké dans la colonne password_hash (voir docs/DATABASE.md). */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
