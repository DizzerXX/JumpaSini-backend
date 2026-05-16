<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'name'             => fake()->company(),
            'address'          => fake()->address(),
            'latitude'         => fake()->randomFloat(6, 3.0, 3.2),
            'longitude'        => fake()->randomFloat(6, 101.5, 101.8),
            'type'             => fake()->randomElement(['mall', 'restaurant', 'cafe', 'park']),
            'is_halal'         => null,
            'is_halal_verified' => false,
            'google_place_id'  => null,
            'google_rating'    => null,
            'is_active'        => true,
        ];
    }

    public function halal(): static
    {
        return $this->state(fn () => ['is_halal' => true, 'is_halal_verified' => true]);
    }

    public function notHalal(): static
    {
        return $this->state(fn () => ['is_halal' => false]);
    }
}
