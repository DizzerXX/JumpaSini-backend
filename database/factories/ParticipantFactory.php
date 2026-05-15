<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'name'       => fake()->name(),
            'address'    => fake()->streetAddress() . ', Kuala Lumpur',
            'latitude'   => fake()->randomFloat(8, 2.9, 3.3),
            'longitude'  => fake()->randomFloat(8, 101.5, 101.8),
        ];
    }
}
