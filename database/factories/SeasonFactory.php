<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'name' => fake()->sentence(2).' Championship',
            'status' => 'draft',
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
        ];
    }
}