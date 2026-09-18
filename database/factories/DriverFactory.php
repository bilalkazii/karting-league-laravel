<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'nickname' => strtoupper(fake()->lexify('???')),
            'racing_number' => fake()->unique()->numberBetween(1, 999),
            'avatar_color' => fake()->hexColor(),
            'avatar_text_color' => '#ffffff',
            'rating' => fake()->numberBetween(1200, 1900),
        ];
    }
}