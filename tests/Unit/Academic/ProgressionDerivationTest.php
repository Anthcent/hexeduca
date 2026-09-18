<?php

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\Matricula;
use Modules\Academic\Infrastructure\Models\NivelAcademico;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;
use Modules\Users\Infrastructure\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('NivelAcademico hasMany Grado', function () {
    $nivelAcademico = (new NivelAcademico)->grados();

    expect($nivelAcademico)->toBeInstanceOf(HasMany::class)
        ->and($nivelAcademico->getRelated())->toBeInstanceOf(Grado::class);
});

test('OfertaAcademica binds one Grado and one Seccion to one Periodo', function () {
    $oferta = new OfertaAcademica;

    expect($oferta->grado())->toBeInstanceOf(BelongsTo::class)
        ->and($oferta->grado()->getRelated())->toBeInstanceOf(Grado::class)
        ->and($oferta->seccion())->toBeInstanceOf(BelongsTo::class)
        ->and($oferta->seccion()->getRelated())->toBeInstanceOf(Seccion::class)
        ->and($oferta->periodoAcademico())->toBeInstanceOf(BelongsTo::class)
        ->and($oferta->periodoAcademico()->getRelated())->toBeInstanceOf(PeriodoAcademico::class);
});

test('Matricula belongs to OfertaAcademica only — no direct catalog relation exists', function () {
    $matricula = new Matricula;

    expect($matricula->ofertaAcademica())->toBeInstanceOf(BelongsTo::class)
        ->and($matricula->ofertaAcademica()->getRelated())->toBeInstanceOf(OfertaAcademica::class)
        ->and(method_exists($matricula, 'grado'))->toBeFalse()
        ->and(method_exists($matricula, 'seccion'))->toBeFalse();
});

test('a student\'s progression across 3 cycles is derivable purely from Matricula history ordered by PeriodoAcademico, with no promotion table consulted', function () {
    $school = School::factory()->create();
    $student = User::factory()->create();

    $periodoOne = PeriodoAcademico::factory()->create([
        'school_id' => $school->id, 'name' => '2023-2024',
        'starts_on' => '2023-03-01', 'ends_on' => '2023-12-15',
    ]);
    $periodoTwo = PeriodoAcademico::factory()->create([
        'school_id' => $school->id, 'name' => '2024-2025',
        'starts_on' => '2024-03-01', 'ends_on' => '2024-12-15',
    ]);
    $periodoThree = PeriodoAcademico::factory()->create([
        'school_id' => $school->id, 'name' => '2025-2026',
        'starts_on' => '2025-03-01', 'ends_on' => '2025-12-15',
    ]);

    $tercero = Grado::factory()->create(['school_id' => $school->id, 'name' => '3ro', 'order' => 3]);
    $cuarto = Grado::factory()->create(['school_id' => $school->id, 'name' => '4to', 'order' => 4]);
    $quinto = Grado::factory()->create(['school_id' => $school->id, 'name' => '5to', 'order' => 5]);

    $seccionA = Seccion::factory()->create(['school_id' => $school->id, 'name' => 'A']);
    $seccionB = Seccion::factory()->create(['school_id' => $school->id, 'name' => 'B']);

    // Cycle 1: 3ro "A". Cycle 2: 4to "B". Cycle 3: 5to "A" — a student
    // progressing through consecutive grades and switching sections.
    $ofertaOne = OfertaAcademica::factory()->create([
        'school_id' => $school->id, 'periodo_academico_id' => $periodoOne->id,
        'grado_id' => $tercero->id, 'seccion_id' => $seccionA->id,
    ]);
    $ofertaTwo = OfertaAcademica::factory()->create([
        'school_id' => $school->id, 'periodo_academico_id' => $periodoTwo->id,
        'grado_id' => $cuarto->id, 'seccion_id' => $seccionB->id,
    ]);
    $ofertaThree = OfertaAcademica::factory()->create([
        'school_id' => $school->id, 'periodo_academico_id' => $periodoThree->id,
        'grado_id' => $quinto->id, 'seccion_id' => $seccionA->id,
    ]);

    // Insert out of chronological order to prove derivation relies on the
    // period ordering, not insertion order.
    Matricula::factory()->create([
        'school_id' => $school->id, 'periodo_academico_id' => $periodoThree->id,
        'oferta_academica_id' => $ofertaThree->id, 'student_id' => $student->id,
    ]);
    Matricula::factory()->create([
        'school_id' => $school->id, 'periodo_academico_id' => $periodoOne->id,
        'oferta_academica_id' => $ofertaOne->id, 'student_id' => $student->id,
    ]);
    Matricula::factory()->create([
        'school_id' => $school->id, 'periodo_academico_id' => $periodoTwo->id,
        'oferta_academica_id' => $ofertaTwo->id, 'student_id' => $student->id,
    ]);

    $progression = Matricula::withoutTenantScope()->withoutActivePeriodoScope()
        ->where('student_id', $student->id)
        ->join('periodos_academicos', 'periodos_academicos.id', '=', 'matriculas.periodo_academico_id')
        ->join('ofertas_academicas', 'ofertas_academicas.id', '=', 'matriculas.oferta_academica_id')
        ->join('grados', 'grados.id', '=', 'ofertas_academicas.grado_id')
        ->orderBy('periodos_academicos.starts_on')
        ->select([
            'periodos_academicos.name as periodo_name',
            'grados.name as grado_name',
        ])
        ->get();

    expect($progression->pluck('periodo_name')->all())->toBe(['2023-2024', '2024-2025', '2025-2026'])
        ->and($progression->pluck('grado_name')->all())->toBe(['3ro', '4to', '5to']);
});
