<?php

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriod;
use App\AcademicPeriod\Scopes\AcademicPeriodScope;
use App\Tenancy\Models\School;
use App\Tenancy\Scopes\TenantScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Tests\Support\Models\PeriodScopedFixture;

uses(RefreshDatabase::class);

function setActivePeriodPSC(PeriodoAcademico $periodo): void
{
    app(AcademicPeriodContext::class)->set(new ActivePeriod(
        id: $periodo->id,
        name: $periodo->name,
        startsOn: $periodo->starts_on,
        endsOn: $periodo->ends_on,
    ));
}

beforeEach(function () {
    Schema::create('period_scoped_fixtures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('school_id');
        $table->unsignedBigInteger('periodo_academico_id');
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('period_scoped_fixtures');
});

test('both scopes apply together: query returns only current school and current period rows', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoOneSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoTwoSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoOneSchoolTwo = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    $match = PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoOneSchoolOne->id,
        'name' => 'match',
    ]);
    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoTwoSchoolOne->id,
        'name' => 'same school other period',
    ]);
    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoOneSchoolTwo->id,
        'name' => 'other school',
    ]);

    app(TenantContext::class)->set($schoolOne);
    setActivePeriodPSC($periodoOneSchoolOne);

    expect(PeriodScopedFixture::all())->toHaveCount(1)
        ->and(PeriodScopedFixture::first()->id)->toBe($match->id);
});

test('opting out of the period scope still tenant-filters', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoTwo = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoOtherSchool = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoOne->id,
        'name' => 'school one period one',
    ]);
    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoTwo->id,
        'name' => 'school one period two',
    ]);
    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoOtherSchool->id,
        'name' => 'school two',
    ]);

    app(TenantContext::class)->set($schoolOne);
    setActivePeriodPSC($periodoOne);

    // Bypassing ONLY the period scope must still tenant-filter: both
    // school-one rows are visible (period scope dropped), school-two's
    // row remains invisible (tenant scope still active).
    expect(PeriodScopedFixture::withoutActivePeriodScope()->count())->toBe(2);
});

test('opting out of the tenant scope still period-filters', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoOtherSchool = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoOne->id,
        'name' => 'school one',
    ]);
    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoOne->id,
        'name' => 'school two same periodo id (cross-tenant collision by id only)',
    ]);
    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoOtherSchool->id,
        'name' => 'school two own periodo',
    ]);

    app(TenantContext::class)->set($schoolOne);
    setActivePeriodPSC($periodoOne);

    // Bypassing ONLY the tenant scope must still period-filter: rows from
    // both schools sharing periodo_academico_id = $periodoOne->id are
    // visible, the third row (different periodo) is not.
    expect(PeriodScopedFixture::withoutTenantScope()->count())->toBe(2);
});

test('neither scope silently drops the other: the remaining where clause always keys the un-dropped scope', function () {
    $schoolOne = School::factory()->create();
    $periodoOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);

    app(TenantContext::class)->set($schoolOne);
    setActivePeriodPSC($periodoOne);

    $withBoth = PeriodScopedFixture::query();
    expect($withBoth->removedScopes())->toBeEmpty();

    $findWhere = fn (array $wheres, string $column) => collect($wheres)->first(fn (array $where) => ($where['column'] ?? null) === $column);

    $withoutPeriodo = PeriodScopedFixture::withoutActivePeriodScope();
    $tenantWhere = $findWhere($withoutPeriodo->toBase()->wheres, 'period_scoped_fixtures.school_id');
    expect($withoutPeriodo->removedScopes())->toBe([AcademicPeriodScope::class])
        ->and($tenantWhere)->not->toBeNull()
        ->and($tenantWhere['value'])->toBe($schoolOne->id);

    $withoutTenant = PeriodScopedFixture::withoutTenantScope();
    $periodoWhere = $findWhere($withoutTenant->toBase()->wheres, 'period_scoped_fixtures.periodo_academico_id');
    expect($withoutTenant->removedScopes())->toBe([TenantScope::class])
        ->and($periodoWhere)->not->toBeNull()
        ->and($periodoWhere['value'])->toBe($periodoOne->id);
});
