<?php

namespace Database\Factories;

use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function definition(): array
    {
        return [
            'short_code'     => strtoupper(fake()->lexify('????????')),
            'transport_mode' => fake()->randomElement(['car', 'public_transport']),
            'venue_type'     => fake()->randomElement(['mall', 'restaurant', 'cafe', 'park', 'beach', 'waterfall', 'river']),
            'halal_filter'   => true,
            'status'         => 'pending',
            'expires_at'     => now()->addHours(48),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subHour(),
            'status'     => 'completed',
        ]);
    }

    public function full(): static
    {
        return $this->afterCreating(function (Session $session) {
            \Database\Factories\ParticipantFactory::new()->count(6)->create([
                'session_id' => $session->id,
            ]);
        });
    }
}
