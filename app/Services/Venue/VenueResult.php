<?php

namespace App\Services\Venue;

use App\Helpers\GeoHelper;
use App\Models\Venue;

class VenueResult
{
    public string $placeId;
    public string $name;
    public string $address;
    public float $lat;
    public float $lng;
    public string $type;
    public ?float $rating;
    public ?bool $isHalal;
    public bool $isHalalVerified;
    public int $distanceMeters;
    public ?string $photoUrl;
    public string $googleMapsUrl;
    public string $source;
    public array $travelTimes = [];
    public ?string $bestForParticipant = null;
    public bool $isBestPick = false;
    public float $score = 0.0;

    public static function fromGoogle(array $place, float $midLat, float $midLng): self
    {
        $self = new self();

        $location = $place['geometry']['location'] ?? ['lat' => 0, 'lng' => 0];

        $self->placeId        = $place['place_id'] ?? '';
        $self->name           = $place['name'] ?? '';
        $self->address        = $place['vicinity'] ?? $place['formatted_address'] ?? '';
        $self->lat            = (float) $location['lat'];
        $self->lng            = (float) $location['lng'];
        $self->type           = $place['_venue_type'] ?? '';
        $self->rating         = isset($place['rating']) ? (float) $place['rating'] : null;
        $self->isHalal        = null;
        $self->isHalalVerified = false;
        $self->distanceMeters = (int) GeoHelper::haversineDistance($midLat, $midLng, $self->lat, $self->lng);
        $self->photoUrl       = self::buildPhotoUrl($place['photos'][0]['photo_reference'] ?? null);
        $self->googleMapsUrl  = 'https://www.google.com/maps/search/?api=1&query=' . $self->lat . ',' . $self->lng . '&query_place_id=' . $self->placeId;
        $self->source         = 'google';

        return $self;
    }

    public static function fromSeeded(Venue $venue, float $midLat, float $midLng): self
    {
        $self = new self();

        $self->placeId        = $venue->google_place_id ?? $venue->id;
        $self->name           = $venue->name;
        $self->address        = $venue->address;
        $self->lat            = $venue->latitude;
        $self->lng            = $venue->longitude;
        $self->type           = $venue->type;
        $self->rating         = $venue->google_rating;
        $self->isHalal        = $venue->is_halal;
        $self->isHalalVerified = (bool) $venue->is_halal_verified;
        $self->distanceMeters = (int) GeoHelper::haversineDistance($midLat, $midLng, $venue->latitude, $venue->longitude);
        $self->photoUrl       = self::buildPhotoUrl($venue->photo_reference);
        $self->googleMapsUrl  = $venue->google_place_id
            ? 'https://www.google.com/maps/search/?api=1&query=' . $venue->latitude . ',' . $venue->longitude . '&query_place_id=' . $venue->google_place_id
            : 'https://www.google.com/maps/search/?api=1&query=' . $venue->latitude . ',' . $venue->longitude;
        $self->source         = 'seeded';

        return $self;
    }

    private static function buildPhotoUrl(?string $photoReference): ?string
    {
        if (!$photoReference) {
            return null;
        }

        $key = config('services.google_maps.key', '');

        return "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photo_reference={$photoReference}&key={$key}";
    }

    public function toArray(): array
    {
        return [
            'place_id'             => $this->placeId,
            'name'                 => $this->name,
            'address'              => $this->address,
            'lat'                  => $this->lat,
            'lng'                  => $this->lng,
            'type'                 => $this->type,
            'rating'               => $this->rating,
            'is_halal'             => $this->isHalal,
            'is_halal_verified'    => $this->isHalalVerified,
            'distance_meters'      => $this->distanceMeters,
            'photo_url'            => $this->photoUrl,
            'google_maps_url'      => $this->googleMapsUrl,
            'source'               => $this->source,
            'travel_times'         => $this->travelTimes,
            'best_for_participant' => $this->bestForParticipant,
            'is_best_pick'         => $this->isBestPick,
            'score'                => round($this->score, 4),
        ];
    }
}
