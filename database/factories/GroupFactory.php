<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'logo_initials' => fake()->regexify('[A-Z]{2}'),
            'logo_color' => '#e11d48',
            'logo_text_color' => '#ffffff',
            'cover_color' => '#27272a',
            'privacy' => 'private',
            'created_by' => Driver::factory(),
        ];
    }
}