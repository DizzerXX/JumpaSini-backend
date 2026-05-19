<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'type',
        'is_halal',
        'is_halal_verified',
        'google_place_id',
        'google_rating',
        'phone',
        'website',
        'opening_hours',
        'photo_reference',
        'is_active',
        'admin_notes',
    ];

    protected $casts = [
        'opening_hours'    => 'array',
        'is_halal'         => 'boolean',
        'is_halal_verified' => 'boolean',
        'is_active'        => 'boolean',
        'latitude'         => 'float',
        'longitude'        => 'float',
        'google_rating'    => 'float',
    ];

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeHalal(Builder $query): void
    {
        $query->where('is_halal', true);
    }

    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}
