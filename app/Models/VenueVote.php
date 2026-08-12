<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueVote extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id',
        'participant_id',
        'google_place_id',
        'venue_name',
        'participant_name',
        'vote',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_id');
    }

    public function scopeForSession(Builder $query, string $sessionId): void
    {
        $query->where('session_id', $sessionId);
    }

    public function scopeForVenue(Builder $query, string $googlePlaceId): void
    {
        $query->where('google_place_id', $googlePlaceId);
    }
}
