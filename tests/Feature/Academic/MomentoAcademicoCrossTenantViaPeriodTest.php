<?php

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academic\Infrastructure\Models\MomentoAcademico;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Period\PeriodoContext;

uses(RefreshDatabase::class);

test('MomentoAcademico has no school_id column: tenant isolation is transitive via its period', function () {
    $columns = Schema::getColumnListing('momentos_academicos');

    expect($columns)->not->toContain('school_id');
});

test('momentos of another school\'s period are invisible under the active period', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoSchoolTwo = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    $momentoSchoolOne = MomentoAcademico::factory()->create([
        'periodo_academico_id' => $periodoSchoolOne->id,
    ]);
    MomentoAcademico::factory()->create([
        'periodo_academico_id' => $periodoSchoolTwo->id,
    ]);

    app(TenantContext::class)->set($schoolOne);
    app(PeriodoContext::class)->set($periodoSchoolOne);

    expect(MomentoAcademico::all())->toHaveCount(1)
        ->and(MomentoAcademico::first()->id)->toBe($momentoSchoolOne->id);
});

test('tenant isolation for MomentoAcademico holds transitively: querying without period scope still exposes both schools\' momentos, proving isolation depends on the active period, not a school_id column', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoSchoolTwo = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    MomentoAcademico::factory()->create(['periodo_academico_id' => $periodoSchoolOne->id]);
    MomentoAcademico::factory()->create(['periodo_academico_id' => $periodoSchoolTwo->id]);

    app(TenantContext::class)->set($schoolOne);
    app(PeriodoContext::class)->set($periodoSchoolOne);

    // With the active period bound, only school one's momento is visible.
    expect(MomentoAcademico::all())->toHaveCount(1);

    // Bypassing the period scope explicitly exposes momentos across every
    // school — proving MomentoAcademico itself carries no school_id guard,
    // and safety comes entirely from PeriodoScope + the periodo relationship.
    expect(MomentoAcademico::withoutActivePeriodoScope()->count())->toBe(2);
});
