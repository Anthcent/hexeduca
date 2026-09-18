<?php

namespace Database\Factories;

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\NivelAcademico;

/**
 * @extends Factory<Grado>
 */
class GradoFactory extends Factory
{
    /**
     * @var class-string<Grado>
     */
    protected $model = Grado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'nivel_academico_id' => NivelAcademico::factory(),
            'name' => fake()->unique()->word().' '.fake()->unique()->numberBetween(1, 9999),
            'order' => fake()->numberBetween(1, 12),
        ];
    }
}
