<?php

namespace Database\Factories;

use App\ModulePlatform\Models\SchoolModule;
use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolModule>
 */
class SchoolModuleFactory extends Factory
{
    /**
     * @var class-string<SchoolModule>
     */
    protected $model = SchoolModule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'module_key' => 'sections',
            'enabled' => true,
        ];
    }
}
