<?php

/**
 * Characterization test (Fase 0 del refactor arquitectónico, ver
 * C:\Users\DELL 3380\.claude\plans\soft-imagining-pascal.md). Congela el
 * comportamiento HTTP actual de las rutas academic/catalogos y
 * academic/periodos/* ANTES de mover PeriodoAcademicoController y
 * CatalogosController a Modules/AcademicPeriods (Fase 2). Ningún test aquí
 * cubría antes el GET de catálogos ni el CRUD+activate de periodos a nivel
 * de ruta HTTP real — solo existía cobertura vía invocación directa del
 * middleware o del caso de uso. Estas rutas deben responder EXACTAMENTE
 * igual durante todas las fases intermedias del refactor (compatibilidad,
 * punto 20 del pedido).
 */

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\AcademicPeriods\Domain\Events\AcademicPeriodActivated;
use Modules\AcademicPeriods\Public\Events\AcademicPeriodActivated as IntegrationAcademicPeriodActivated;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function academicRouteUrl(School $school, string $path): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}{$path}";
}

function staffAdminForSchool(School $school): User
{
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');

    return $staff;
}

test('GET academic/catalogos renders the four catalogs for an authenticated staff/admin', function () {
    $school = School::factory()->create();
    $staff = staffAdminForSchool($school);
    PeriodoAcademico::factory()->create(['school_id' => $school->id]);

    $this->actingAs($staff)
        ->get(academicRouteUrl($school, '/academic/catalogos'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academic::Catalogos')
            ->has('niveles')
            ->has('grados')
            ->has('secciones')
            ->has('periodos')
        );
});

test('GET academic/catalogos redirects a guest to login', function () {
    $school = School::factory()->create();

    $this->get(academicRouteUrl($school, '/academic/catalogos'))
        ->assertRedirect();
});

test('POST academic/periodos creates an inactive period', function () {
    $school = School::factory()->create();
    $staff = staffAdminForSchool($school);

    $this->actingAs($staff)
        ->post(academicRouteUrl($school, '/academic/periodos'), [
            'name' => '2026-2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('periodos_academicos', [
        'school_id' => $school->id,
        'name' => '2026-2027',
        'is_active' => false,
    ]);
});

test('POST academic/periodos rejects ends_on before starts_on', function () {
    $school = School::factory()->create();
    $staff = staffAdminForSchool($school);

    $this->actingAs($staff)
        ->post(academicRouteUrl($school, '/academic/periodos'), [
            'name' => 'invalid range',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-01-01',
        ])
        ->assertSessionHasErrors('ends_on');
});

test('PUT academic/periodos/{id} updates name and dates', function () {
    $school = School::factory()->create();
    $staff = staffAdminForSchool($school);
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'name' => 'old']);

    $this->actingAs($staff)
        ->put(academicRouteUrl($school, "/academic/periodos/{$periodo->id}"), [
            'name' => 'renamed',
            'starts_on' => $periodo->starts_on,
            'ends_on' => $periodo->ends_on,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('periodos_academicos', ['id' => $periodo->id, 'name' => 'renamed']);
});

test('POST academic/periodos/{id}/activate deactivates the previous active period and activates the target one', function () {
    $school = School::factory()->create();
    $staff = staffAdminForSchool($school);
    $currentlyActive = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    $toActivate = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => false]);
    Event::fake([AcademicPeriodActivated::class]);

    $this->actingAs($staff)
        ->post(academicRouteUrl($school, "/academic/periodos/{$toActivate->id}/activate"))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('periodos_academicos', ['id' => $toActivate->id, 'is_active' => true]);
    $this->assertDatabaseHas('periodos_academicos', ['id' => $currentlyActive->id, 'is_active' => false]);
    $outbox = DB::table('integration_outbox_events')
        ->where('event_name', 'academic_period.activated')
        ->sole();
    expect($outbox->event_class)->toBe(IntegrationAcademicPeriodActivated::class)
        ->and((int) $outbox->aggregate_id)->toBe($toActivate->id);
    Event::assertDispatchedTimes(AcademicPeriodActivated::class, 1);
});

test('legacy period activation rolls back period state and event when authoritative outbox recording fails', function () {
    $school = School::factory()->create();
    $staff = staffAdminForSchool($school);
    $currentlyActive = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    $toActivate = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => false]);
    Event::fake([AcademicPeriodActivated::class]);
    DB::statement(<<<'SQL'
        CREATE TRIGGER fail_legacy_period_activation_outbox
        BEFORE INSERT ON integration_outbox_events
        WHEN NEW.event_name = 'academic_period.activated'
        BEGIN
            SELECT RAISE(ABORT, 'forced outbox failure');
        END
    SQL);

    expect(fn () => $this->withoutExceptionHandling()
        ->actingAs($staff)
        ->post(academicRouteUrl($school, "/academic/periodos/{$toActivate->id}/activate")))
        ->toThrow(QueryException::class);

    expect((bool) $currentlyActive->fresh()->is_active)->toBeTrue()
        ->and((bool) $toActivate->fresh()->is_active)->toBeFalse();
    expect(DB::table('integration_outbox_events')->where('event_name', 'academic_period.activated')->count())->toBe(0);
    Event::assertNotDispatched(AcademicPeriodActivated::class);
});

test('DELETE academic/periodos/{id} removes a period with no associated data', function () {
    $school = School::factory()->create();
    $staff = staffAdminForSchool($school);
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);

    $this->actingAs($staff)
        ->delete(academicRouteUrl($school, "/academic/periodos/{$periodo->id}"))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('periodos_academicos', ['id' => $periodo->id]);
});

test('academic/periodos routes reject a non staff/admin authenticated user', function () {
    $school = School::factory()->create();
    $plainUser = User::factory()->create(['school_id' => $school->id]);
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);

    $this->actingAs($plainUser)
        ->post(academicRouteUrl($school, '/academic/periodos'), [
            'name' => 'nope',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-06-01',
        ])
        ->assertForbidden();

    $this->actingAs($plainUser)
        ->post(academicRouteUrl($school, "/academic/periodos/{$periodo->id}/activate"))
        ->assertForbidden();
});
