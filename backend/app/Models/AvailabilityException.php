<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityException extends Model
{
    public const TYPE_CLOSED = 'closed';

    public const TYPE_CUSTOM = 'custom';

    public $timestamps = false;

    protected $fillable = ['date', 'type', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
