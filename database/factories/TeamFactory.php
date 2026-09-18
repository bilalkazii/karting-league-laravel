<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'group_id' => \App\Models\Group::factory(),
            'name' => fake()->catchPhrase(),
            'logo_initials' => fake()->regexify('[A-Z]{2}'),
            'logo_color' => '#e11d48',
            'logo_text_color' => '#ffffff',
        ];
    }
}