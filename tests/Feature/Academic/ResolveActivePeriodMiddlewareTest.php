<?php

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriodResolver;
use App\AcademicPeriod\Http\Middleware\ResolveActivePeriod;
use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\AcademicPeriods\Infrastructure\Http\Resolvers\EloquentActivePeriodResolver;

uses(RefreshDatabase::class);

function runResolveActivePeriod(): Response
{
    return app(ResolveActivePeriod::class)->handle(
        Request::create('/'),
        fn ($request) => new Response('ok')
    );
}

test('middleware binds the school active period into AcademicPeriodContext', function () {
    $school = School::factory()->create();
    $inactive = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => false]);
    $active = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);

    app(TenantContext::class)->set($school);

    runResolveActivePeriod();

    $context = app(AcademicPeriodContext::class);

    expect($context->hasPeriod())->toBeTrue()
        ->and($context->current()->id)->toBe($active->id)
        ->and($context->current()->id)->not->toBe($inactive->id);
});

test('middleware resolves via Modules\AcademicPeriods own resolver binding (Fase 2: no more legacy bridge)', function () {
    $school = School::factory()->create();
    $active = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);

    app(TenantContext::class)->set($school);

    runResolveActivePeriod();

    expect(app(ActivePeriodResolver::class))
        ->toBeInstanceOf(EloquentActivePeriodResolver::class);
});

test('middleware is a no-op when no period is flagged active: no failure, AcademicPeriodContext stays unbound', function () {
    $school = School::factory()->create();
    PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => false]);

    app(TenantContext::class)->set($school);

    $response = runResolveActivePeriod();

    expect($response->getContent())->toBe('ok')
        ->and(app(AcademicPeriodContext::class)->hasPeriod())->toBeFalse();
});

test('middleware is a no-op when no tenant is bound at all', function () {
    $response = runResolveActivePeriod();

    expect($response->getContent())->toBe('ok')
        ->and(app(AcademicPeriodContext::class)->hasPeriod())->toBeFalse();
});

test('middleware only activates the requesting school own active period, not another school active period', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id, 'is_active' => true]);
    $expected = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id, 'is_active' => true]);

    app(TenantContext::class)->set($schoolOne);

    runResolveActivePeriod();

    expect(app(AcademicPeriodContext::class)->current()->id)->toBe($expected->id);
});
