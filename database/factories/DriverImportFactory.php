<?php

namespace Database\Factories;

use App\Models\DriverImport;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverImport>
 */
class DriverImportFactory extends Factory
{
    protected $model = DriverImport::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'created_by' => User::factory(),
            'original_filename' => 'drivers.csv',
            'status' => DriverImport::PREVIEW,
            'confirmed_at' => null,
        ];
    }
}
