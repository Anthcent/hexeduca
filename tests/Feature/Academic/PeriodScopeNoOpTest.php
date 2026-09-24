<?php

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Tests\Support\Models\PeriodScopedFixture;

uses(RefreshDatabase::class);

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

test('no active period bound: PeriodoScope adds no where, but TenantScope still filters cross-tenant rows', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $periodoOneSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoTwoSchoolOne = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id]);
    $periodoSchoolTwo = PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id]);

    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoOneSchoolOne->id,
        'name' => 'school one period one',
    ]);
    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolOne->id,
        'periodo_academico_id' => $periodoTwoSchoolOne->id,
        'name' => 'school one period two',
    ]);
    PeriodScopedFixture::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolTwo->id,
        'periodo_academico_id' => $periodoSchoolTwo->id,
        'name' => 'school two',
    ]);

    // TenantContext bound, PeriodoContext left unbound entirely (no set()
    // call) — simulates a request/console context with a resolved
    // tenant but no active period (e.g. a school between cycles).
    app(TenantContext::class)->set($schoolOne);

    // Cross-period rows within the current tenant ARE visible (PeriodoScope
    // is a silent no-op), but the cross-tenant row is still excluded.
    expect(PeriodScopedFixture::all())->toHaveCount(2)
        ->and(PeriodScopedFixture::pluck('school_id')->unique()->all())->toBe([$schoolOne->id]);
});

test('no active period bound: the query has no periodo_academico_id where clause at all', function () {
    $school = School::factory()->create();
    app(TenantContext::class)->set($school);

    $wheres = PeriodScopedFixture::query()->toBase()->wheres;

    $periodoWheres = array_filter($wheres, fn (array $where) => ($where['column'] ?? null) === 'period_scoped_fixtures.periodo_academico_id');

    expect($periodoWheres)->toBeEmpty();
});
