<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'short_code',
        'transport_mode',
        'venue_type',
        'halal_filter',
        'status',
        'expires_at',
        'midpoint_lat',
        'midpoint_lng',
        'midpoint_calculated_at',
        'midpoint_is_fair',
        'midpoint_travel_times',
        'session_confirmed_venue',
        'session_confirmed_at',
        'voting_starts_at',
        'voting_ends_at',
        'voting_confirmed_venue',
    ];

    protected $appends = ['shareable_url'];

    protected $casts = [
        'halal_filter'           => 'boolean',
        'expires_at'             => 'datetime',
        'midpoint_lat'           => 'float',
        'midpoint_lng'           => 'float',
        'midpoint_calculated_at' => 'datetime',
        'midpoint_is_fair'       => 'boolean',
        'midpoint_travel_times'  => 'array',
        'session_confirmed_at'   => 'datetime',
        'voting_starts_at'       => 'datetime',
        'voting_ends_at'         => 'datetime',
    ];

    public function shareableUrl(): Attribute
    {
        return Attribute::get(
            fn () => config('jumpa.urls.shareable_base') . $this->short_code
        );
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'session_id');
    }

    /** Scope: sessions that have not expired and are not completed. */
    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now())
              ->where('status', '!=', 'completed');
    }
}
