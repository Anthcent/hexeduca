<?php

namespace Database\Factories;

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Infrastructure\Models\NivelAcademico;

/**
 * @extends Factory<NivelAcademico>
 */
class NivelAcademicoFactory extends Factory
{
    /**
     * @var class-string<NivelAcademico>
     */
    protected $model = NivelAcademico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->randomElement(['Inicial', 'Primaria', 'Secundaria']).' '.fake()->unique()->numberBetween(1, 9999),
        ];
    }
}
