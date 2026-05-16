<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
}
