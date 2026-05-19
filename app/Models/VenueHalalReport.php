<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueHalalReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'venue_id',
        'google_place_id',
        'reported_as_halal',
        'session_id',
    ];

    protected $casts = [
        'reported_as_halal' => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
