<?php

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Academic\Infrastructure\Http\Middleware\ResolveActivePeriodo;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Period\PeriodoContext;

uses(RefreshDatabase::class);

function runResolveActivePeriodo(): Response
{
    return (new ResolveActivePeriodo)->handle(
        Request::create('/'),
        fn ($request) => new Response('ok')
    );
}

test('middleware binds the school active period into PeriodoContext', function () {
    $school = School::factory()->create();
    $inactive = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => false]);
    $active = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);

    app(TenantContext::class)->set($school);

    runResolveActivePeriodo();

    $context = app(PeriodoContext::class);

    expect($context->hasPeriodo())->toBeTrue()
        ->and($context->current()->id)->toBe($active->id)
        ->and($context->current()->id)->not->toBe($inactive->id);
});

test('middleware is a no-op when no period is flagged active: no failure, PeriodoContext stays unbound', function () {
    $school = School::factory()->create();
    PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => false]);

    app(TenantContext::class)->set($school);

    $response = runResolveActivePeriodo();

    expect($response->getContent())->toBe('ok')
        ->and(app(PeriodoContext::class)->hasPeriodo())->toBeFalse();
});

test('middleware is a no-op when no tenant is bound at all', function () {
    $response = runResolveActivePeriodo();

    expect($response->getContent())->toBe('ok')
        ->and(app(PeriodoContext::class)->hasPeriodo())->toBeFalse();
});

test('middleware only activates the requesting school own active period, not another school active period', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    PeriodoAcademico::factory()->create(['school_id' => $schoolTwo->id, 'is_active' => true]);
    $expected = PeriodoAcademico::factory()->create(['school_id' => $schoolOne->id, 'is_active' => true]);

    app(TenantContext::class)->set($schoolOne);

    runResolveActivePeriodo();

    expect(app(PeriodoContext::class)->current()->id)->toBe($expected->id);
});
