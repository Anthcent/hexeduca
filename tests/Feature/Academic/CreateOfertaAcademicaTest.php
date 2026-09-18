<?php

use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Application\DTOs\CreateOfertaAcademicaData;
use Modules\Academic\Application\UseCases\CreateOfertaAcademica;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;

uses(RefreshDatabase::class);

test('rejects a duplicate (periodo, grado, seccion) combination', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);

    $useCase = app(CreateOfertaAcademica::class);

    $data = new CreateOfertaAcademicaData(
        schoolId: $school->id,
        periodoAcademicoId: $periodo->id,
        gradoId: $grado->id,
        seccionId: $seccion->id,
        teacherId: null,
        capacity: 30,
    );

    $useCase->handle($data);

    expect(fn () => $useCase->handle($data))
        ->toThrow(DomainException::class, 'An OfertaAcademica already exists for this periodo/grado/seccion combination.');
});

test('the same catalog Grado+Seccion pair reused across two periods yields two distinct offerings', function () {
    $school = School::factory()->create();
    $periodoOne = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $periodoTwo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);

    $useCase = app(CreateOfertaAcademica::class);

    $ofertaPeriodoOne = $useCase->handle(new CreateOfertaAcademicaData(
        schoolId: $school->id,
        periodoAcademicoId: $periodoOne->id,
        gradoId: $grado->id,
        seccionId: $seccion->id,
        teacherId: null,
        capacity: 20,
    ));

    $ofertaPeriodoTwo = $useCase->handle(new CreateOfertaAcademicaData(
        schoolId: $school->id,
        periodoAcademicoId: $periodoTwo->id,
        gradoId: $grado->id,
        seccionId: $seccion->id,
        teacherId: null,
        capacity: 35,
    ));

    expect($ofertaPeriodoOne->id())->not->toBe($ofertaPeriodoTwo->id())
        ->and($ofertaPeriodoOne->capacity()->limit())->toBe(20)
        ->and($ofertaPeriodoTwo->capacity()->limit())->toBe(35);
});
