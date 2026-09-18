<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Group;
use App\Models\Race;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Race>
 */
class RaceFactory extends Factory
{
    protected $model = Race::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'name' => fake()->sentence(3),
            'venue_name' => fake()->company().' Karting',
            'date' => fake()->date(),
            'start_time' => fake()->time('H:i'),
            'format' => 'sprint',
            'status' => 'draft',
            'organizer_id' => Driver::factory(),
            'qualifying_lap_count' => 1,
            'rules' => '',
        ];
    }
}