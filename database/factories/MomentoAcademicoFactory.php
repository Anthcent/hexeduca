<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Infrastructure\Models\MomentoAcademico;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;

/**
 * @extends Factory<MomentoAcademico>
 */
class MomentoAcademicoFactory extends Factory
{
    /**
     * @var class-string<MomentoAcademico>
     */
    protected $model = MomentoAcademico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'periodo_academico_id' => PeriodoAcademico::factory(),
            'name' => fake()->unique()->word().' '.fake()->unique()->numberBetween(1, 9999),
            'order' => fake()->numberBetween(1, 3),
            'starts_on' => $startsOn,
            'ends_on' => (clone $startsOn)->modify('+3 months'),
        ];
    }
}
