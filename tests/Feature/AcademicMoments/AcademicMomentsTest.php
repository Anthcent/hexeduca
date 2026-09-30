<?php

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Tests\Feature\AcademicMoments\AcademicMomentsFixtures as M;
use Tests\Feature\Subjects\SubjectsFixtures as S;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-28 10:00:00');
    $this->seed(RoleAndPermissionSeeder::class);

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();
    $this->seed(ModulePlatformSeeder::class);

    $this->staff = S::user($this->school, 'staff/admin');
    $this->period = S::period($this->school, '2026', true);
    $this->foreignPeriod = S::period($this->otherSchool, '2026', true);
});

function momentPayload(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Primer momento',
        'order' => 1,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-12-15',
        'grading_opens_on' => '2026-12-01',
        'grading_closes_on' => '2026-12-20',
    ];
}

test('a moment is always created in the school\'s active period, never in a period from the request', function () {
    $this->actingAs($this->staff)
        ->post(M::url($this->school), momentPayload(['academic_period_id' => $this->foreignPeriod, 'periodo_academico_id' => $this->foreignPeriod]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Momento académico creado.');

    $moment = DB::table('momentos_academicos')->sole();
    expect($moment->periodo_academico_id)->toBe($this->period)
        ->and(DB::table('momentos_academicos')->where('periodo_academico_id', $this->foreignPeriod)->exists())->toBeFalse()
        ->and(substr($moment->grading_opens_on, 0, 10))->toBe('2026-12-01')
        ->and(substr($moment->grading_closes_on, 0, 10))->toBe('2026-12-20');
});

test('without an active period no moment is created', function () {
    DB::table('periodos_academicos')->where('id', $this->period)->update(['is_active' => false]);

    $this->actingAs($this->staff)
        ->post(M::url($this->school), momentPayload(['academic_period_id' => $this->foreignPeriod]))
        ->assertSessionHas('error', 'No hay un período académico activo.');

    expect(DB::table('momentos_academicos')->count())->toBe(0);
});

test('the grading window takes both dates or none, and cannot close before it opens', function (array $window, string $field, string $message) {
    $this->actingAs($this->staff)
        ->post(M::url($this->school), momentPayload($window))
        ->assertSessionHasErrors([$field => $message]);

    expect(DB::table('momentos_academicos')->count())->toBe(0);
})->with([
    'only the opening' => [['grading_closes_on' => null], 'grading_closes_on', 'Indica hasta cuándo se cargan notas.'],
    'only the closing' => [['grading_opens_on' => null], 'grading_opens_on', 'Indica desde cuándo se cargan notas.'],
    'closes before it opens' => [['grading_opens_on' => '2026-12-10', 'grading_closes_on' => '2026-12-09'], 'grading_closes_on', 'El cierre de carga debe ser igual o posterior a la apertura.'],
]);

test('a moment without a grading window is valid', function () {
    $this->actingAs($this->staff)
        ->post(M::url($this->school), momentPayload(['grading_opens_on' => null, 'grading_closes_on' => null]))
        ->assertSessionHasNoErrors();

    expect(DB::table('momentos_academicos')->sole()->grading_opens_on)->toBeNull();
});

test('updating a moment edits its name and grading window', function () {
    $moment = M::moment($this->period, 'Lapso 1');

    $this->actingAs($this->staff)
        ->put(M::url($this->school, "/{$moment}"), momentPayload(['name' => 'Primer lapso', 'grading_opens_on' => '2026-10-01', 'grading_closes_on' => '2026-10-10']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Momento académico actualizado.');

    $row = DB::table('momentos_academicos')->sole();
    expect($row->name)->toBe('Primer lapso')
        ->and($row->periodo_academico_id)->toBe($this->period)
        ->and(substr($row->grading_opens_on, 0, 10))->toBe('2026-10-01')
        ->and(substr($row->grading_closes_on, 0, 10))->toBe('2026-10-10');

    $this->actingAs($this->staff)
        ->put(M::url($this->school, "/{$moment}"), momentPayload(['grading_opens_on' => null, 'grading_closes_on' => null]))
        ->assertSessionHasNoErrors();
    expect(DB::table('momentos_academicos')->sole()->grading_closes_on)->toBeNull();
});

test('another school\'s moment can be neither listed, edited nor deleted', function (bool $withActivePeriod) {
    if (! $withActivePeriod) {
        DB::table('periodos_academicos')->where('id', $this->period)->update(['is_active' => false]);
    }

    $foreign = M::moment($this->foreignPeriod, 'Ajeno');

    $this->actingAs($this->staff)->get(M::url($this->school))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('moments', []));
    $this->actingAs($this->staff)->put(M::url($this->school, "/{$foreign}"), momentPayload(['name' => 'Tomado']))->assertNotFound();
    $this->actingAs($this->staff)->delete(M::url($this->school, "/{$foreign}"))->assertNotFound();

    expect(DB::table('momentos_academicos')->where('id', $foreign)->value('name'))->toBe('Ajeno');
})->with(['with an active period' => true, 'without an active period' => false]);

test('the public reader only returns the school\'s moments, by order', function () {
    $second = M::moment($this->period, 'Segundo', 2);
    $first = M::moment($this->period, 'Primero', 1, '2026-10-01', '2026-10-10');
    $otherPeriod = S::period($this->school, '2025', false, '2025-01-01', '2025-11-30');
    $old = M::moment($otherPeriod, 'Viejo');
    $foreign = M::moment($this->foreignPeriod, 'Ajeno');

    $reader = app(AcademicMomentReader::class);
    $moments = $reader->forPeriod($this->school->id, $this->period);

    expect(array_map(fn ($m) => $m->id, $moments))->toBe([$first, $second])
        ->and($moments[0]->periodId)->toBe($this->period)
        ->and($moments[0]->gradingOpensOn)->toBe('2026-10-01')
        ->and($moments[0]->gradingClosesOn)->toBe('2026-10-10')
        ->and($moments[1]->gradingOpensOn)->toBeNull()
        ->and($reader->forPeriod($this->school->id, $this->foreignPeriod))->toBe([])
        ->and($reader->findForSchool($old, $this->school->id)?->name)->toBe('Viejo')
        ->and($reader->findForSchool($foreign, $this->school->id))->toBeNull()
        ->and($reader->findForSchool($foreign, $this->otherSchool->id)?->name)->toBe('Ajeno');
});
