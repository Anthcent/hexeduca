<?php

namespace Database\Factories;

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;

/**
 * @extends Factory<OfertaAcademica>
 */
class OfertaAcademicaFactory extends Factory
{
    /**
     * @var class-string<OfertaAcademica>
     */
    protected $model = OfertaAcademica::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'periodo_academico_id' => PeriodoAcademico::factory(),
            'grado_id' => Grado::factory(),
            'seccion_id' => Seccion::factory(),
            'teacher_id' => null,
            'capacity' => fake()->numberBetween(20, 40),
        ];
    }
}
