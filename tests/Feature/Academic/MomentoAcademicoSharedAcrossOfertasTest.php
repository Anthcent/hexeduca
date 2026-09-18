<?php

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Models\MomentoAcademico;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Period\PeriodoContext;

uses(RefreshDatabase::class);

test('the same MomentoAcademico records are referenced by every OfertaAcademica within a period', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);

    $momentos = MomentoAcademico::factory()->count(3)->create([
        'periodo_academico_id' => $periodo->id,
    ]);

    app(TenantContext::class)->set($school);
    app(PeriodoContext::class)->set($periodo);

    $ofertaOne = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
    ]);
    $ofertaTwo = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
    ]);

    // MomentoAcademico is global within its period — it has no FK to
    // OfertaAcademica. "Shared across offerings" means every offering in
    // the same period resolves to the exact same 3 momento records via the
    // shared periodo_academico_id, not via a per-offering copy.
    $momentosForOfertaOne = MomentoAcademico::where('periodo_academico_id', $ofertaOne->periodo_academico_id)->pluck('id')->sort()->values();
    $momentosForOfertaTwo = MomentoAcademico::where('periodo_academico_id', $ofertaTwo->periodo_academico_id)->pluck('id')->sort()->values();

    expect($momentosForOfertaOne)->toHaveCount(3)
        ->and($momentosForOfertaOne->all())->toBe($momentosForOfertaTwo->all())
        ->and($momentosForOfertaOne->all())->toBe($momentos->pluck('id')->sort()->values()->all());
});
