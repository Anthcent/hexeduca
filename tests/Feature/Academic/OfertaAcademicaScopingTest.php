<?php

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\Matricula;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;
use Modules\Academic\Infrastructure\Period\PeriodoContext;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

test('scope stacking on the real OfertaAcademica model: query returns only current school and current period rows', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoOneSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoTwoSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoOneSchoolTwo = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    $match = OfertaAcademica::factory()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoOneSchoolOne->id,
    ]);
    OfertaAcademica::factory()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoTwoSchoolOne->id,
    ]);
    OfertaAcademica::factory()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoOneSchoolTwo->id,
    ]);

    app(TenantContext::class)->set($schoolOne);
    app(PeriodoContext::class)->set($periodoOneSchoolOne);

    expect(OfertaAcademica::all())->toHaveCount(1)
        ->and(OfertaAcademica::first()->id)->toBe($match->id);
});

test('independent bypass on the real Matricula model: opting out of the period scope still tenant-filters', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoTwo = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoOtherSchool = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    Matricula::factory()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoOne->id,
    ]);
    Matricula::factory()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoTwo->id,
    ]);
    Matricula::factory()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoOtherSchool->id,
    ]);

    app(TenantContext::class)->set($schoolOne);
    app(PeriodoContext::class)->set($periodoOne);

    expect(Matricula::withoutActivePeriodoScope()->count())->toBe(2);
});

test('independent bypass on the real Matricula model: opting out of the tenant scope still period-filters', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoOtherSchool = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    Matricula::factory()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoOne->id,
    ]);
    Matricula::withoutTenantScope()->withoutActivePeriodoScope()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoOne->id,
        'oferta_academica_id' => OfertaAcademica::withoutTenantScope()->withoutActivePeriodoScope()->create([
            'school_id' => $schoolTwo->id,
            'periodo_academico_id' => $periodoOne->id,
            'grado_id' => Grado::factory()->create(['school_id' => $schoolTwo->id])->id,
            'seccion_id' => Seccion::factory()->create(['school_id' => $schoolTwo->id])->id,
            'capacity' => 10,
        ])->id,
        'student_id' => User::factory()->create()->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);
    Matricula::factory()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoOtherSchool->id,
    ]);

    app(TenantContext::class)->set($schoolOne);
    app(PeriodoContext::class)->set($periodoOne);

    // Rows from both schools sharing periodo_academico_id = $periodoOne->id
    // are visible once the tenant scope alone is dropped; the third row
    // (different periodo) is not.
    expect(Matricula::withoutTenantScope()->count())->toBe(2);
});

test('no period bound: cross-period OfertaAcademica rows are visible but cross-tenant rows are still hidden', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoOneSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoTwoSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoSchoolTwo = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    OfertaAcademica::factory()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoOneSchoolOne->id,
    ]);
    OfertaAcademica::factory()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoTwoSchoolOne->id,
    ]);
    OfertaAcademica::factory()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoSchoolTwo->id,
    ]);

    // TenantContext bound, PeriodoContext deliberately left unbound.
    app(TenantContext::class)->set($schoolOne);

    expect(OfertaAcademica::all())->toHaveCount(2)
        ->and(OfertaAcademica::pluck('school_id')->unique()->all())->toBe([$schoolOne->id]);
});

test('auto-stamp on create: OfertaAcademica inherits school_id and periodo_academico_id from bound contexts', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);

    app(TenantContext::class)->set($school);
    app(PeriodoContext::class)->set($periodo);

    $oferta = OfertaAcademica::create([
        'grado_id' => $grado->id,
        'seccion_id' => $seccion->id,
        'capacity' => 30,
    ]);

    expect($oferta->school_id)->toBe($school->id)
        ->and($oferta->periodo_academico_id)->toBe($periodo->id);
});

test('auto-stamp on create: Matricula inherits school_id and periodo_academico_id from bound contexts', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
    ]);
    $student = User::factory()->create();

    app(TenantContext::class)->set($school);
    app(PeriodoContext::class)->set($periodo);

    $matricula = Matricula::create([
        'oferta_academica_id' => $oferta->id,
        'student_id' => $student->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    expect($matricula->school_id)->toBe($school->id)
        ->and($matricula->periodo_academico_id)->toBe($periodo->id);
});
