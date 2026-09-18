<?php

namespace Database\Factories;

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Infrastructure\Models\Seccion;

/**
 * @extends Factory<Seccion>
 */
class SeccionFactory extends Factory
{
    /**
     * @var class-string<Seccion>
     */
    protected $model = Seccion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->randomLetter(),
        ];
    }
}
