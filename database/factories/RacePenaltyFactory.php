<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Race;
use App\Models\RacePenalty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RacePenalty>
 */
class RacePenaltyFactory extends Factory
{
    protected $model = RacePenalty::class;

    public function definition(): array
    {
        return [
            'race_id' => Race::factory(),
            'driver_id' => Driver::factory(),
            'seconds' => fake()->numberBetween(1, 20),
            'reason' => fake()->sentence(),
            'issued_by' => Driver::factory(),
            'status' => 'issued',
        ];
    }
}