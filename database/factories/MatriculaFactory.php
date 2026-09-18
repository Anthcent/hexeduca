<?php

namespace Database\Factories;

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Infrastructure\Models\Matricula;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Users\Infrastructure\Models\User;

/**
 * @extends Factory<Matricula>
 */
class MatriculaFactory extends Factory
{
    /**
     * @var class-string<Matricula>
     */
    protected $model = Matricula::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'periodo_academico_id' => PeriodoAcademico::factory(),
            'oferta_academica_id' => OfertaAcademica::factory(),
            'student_id' => User::factory(),
            'status' => 'active',
            'enrolled_at' => now(),
        ];
    }
}
