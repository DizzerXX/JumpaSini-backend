<?php

namespace App\Services\Midpoint;

class MidpointResult
{
    public float $lat;
    public float $lng;
    /** @var array<string, int> participant_id => travel seconds */
    public array $travelTimes;
    public bool $isFair;
    public int $iterations;
    public string $transportMode;

    public static function make(array $data): self
    {
        $result = new self();
        $result->lat           = (float) $data['lat'];
        $result->lng           = (float) $data['lng'];
        $result->travelTimes   = $data['travel_times'];
        $result->isFair        = (bool)  $data['is_fair'];
        $result->iterations    = (int)   $data['iterations'];
        $result->transportMode = (string) $data['transport_mode'];

        return $result;
    }
}
