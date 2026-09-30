<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Grades\GradesFixtures as G;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);

    // A plan whose grading window closed in July.
    $this->plan = G::plan($this, $this->math, $this->closedMoment);
    $this->indicators = G::indicatorIds($this->plan);
});

function correctionUrl(object $test, ?int $plan = null): string
{
    return G::url($test->school, '/sheets/'.($plan ?? $test->plan).'/correction');
}

function correctionGrade(object $test, ?int $studentId = null, ?object $user = null, ?int $points = 5)
{
    return $test->actingAs($user ?? $test->teacher)->putJson(G::url($test->school, "/sheets/{$test->plan}/cells"), [
        'student_id' => $studentId ?? $test->s1->id,
        'indicator_id' => $test->indicators[0],
        'points' => $points,
    ]);
}

test('staff opens and closes a correction', function () {
    G::openCorrection($this, $this->plan, '2026-10-05', '  Error de transcripción  ');

    $row = DB::table('grade_corrections')->sole();
    expect([$row->school_id, $row->grade_plan_id, $row->opened_by, $row->reason, $row->expires_at, $row->closed_at])
        ->toBe([$this->school->id, $this->plan, $this->staff->id, 'Error de transcripción', '2026-10-05 23:59:59', null]);

    $this->actingAs($this->staff)->delete(correctionUrl($this))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Corrección cerrada.');

    $row = DB::table('grade_corrections')->sole();
    expect($row->closed_at)->not->toBeNull()
        ->and($row->closed_by)->toBe($this->staff->id);
});

test('a teacher can neither open nor close a correction', function () {
    $this->actingAs($this->teacher)->post(correctionUrl($this), ['reason' => 'Error', 'expires_on' => '2026-10-05'])->assertForbidden();
    expect(DB::table('grade_corrections')->count())->toBe(0);

    G::openCorrection($this, $this->plan, '2026-10-05');
    $this->actingAs($this->teacher)->delete(correctionUrl($this))->assertForbidden();

    expect(DB::table('grade_corrections')->sole()->closed_at)->toBeNull();
});

test('a correction needs a reason', function (?string $reason) {
    $this->actingAs($this->staff)->post(correctionUrl($this), ['reason' => $reason, 'expires_on' => '2026-10-05'])
        ->assertSessionHasErrors(['reason' => 'Indica el motivo de la corrección.']);

    expect(DB::table('grade_corrections')->count())->toBe(0);
})->with(['missing' => [null], 'blank' => ['   ']]);

test('the expiry must be a date from today up to 30 days ahead', function (?string $expiresOn, ?string $error) {
    $response = $this->actingAs($this->staff)->post(correctionUrl($this), ['reason' => 'Error', 'expires_on' => $expiresOn]);

    if ($error === null) {
        $response->assertSessionHasNoErrors();
        expect(DB::table('grade_corrections')->count())->toBe(1);
    } else {
        $response->assertSessionHasErrors($error === 'format'
            ? ['expires_on' => 'Indica hasta qué día estará abierta la corrección.']
            : ['correction' => 'La corrección debe vencer entre hoy y dentro de 30 días.']);
        expect(DB::table('grade_corrections')->count())->toBe(0);
    }
})->with([
    'yesterday' => ['2026-09-27', 'range'],
    'today' => ['2026-09-28', null],
    '30 days ahead' => ['2026-10-28', null],
    '31 days ahead' => ['2026-10-29', 'range'],
    'not a date' => ['28/10/2026', 'format'],
    'missing' => [null, 'format'],
]);

test('opening a second correction closes the first', function () {
    G::openCorrection($this, $this->plan, '2026-10-05', 'Primera');
    G::openCorrection($this, $this->plan, '2026-10-10', 'Segunda');

    $rows = DB::table('grade_corrections')->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->closed_at)->not->toBeNull()
        ->and($rows[0]->closed_by)->toBe($this->staff->id)
        ->and($rows[1]->closed_at)->toBeNull();

    correctionGrade($this)->assertOk();
    expect(DB::table('grade_changes')->sole()->grade_correction_id)->toBe($rows[1]->id);
});

test('a correction on another school\'s plan is 404', function () {
    $foreignPlan = G::foreignPlan($this);

    $this->actingAs($this->staff)->post(correctionUrl($this, $foreignPlan), ['reason' => 'Error', 'expires_on' => '2026-10-05'])->assertNotFound();
    $this->actingAs($this->staff)->delete(correctionUrl($this, $foreignPlan))->assertNotFound();

    expect(DB::table('grade_corrections')->count())->toBe(0);
});

test('a correction allows grading outside the window and ties the change to it', function () {
    correctionGrade($this)->assertStatus(422)->assertExactJson(['message' => 'La carga de notas de este momento está cerrada.']);

    G::openCorrection($this, $this->plan, '2026-10-05');
    $correction = DB::table('grade_corrections')->sole()->id;

    correctionGrade($this)->assertOk()->assertJsonPath('row.referentTotals', [5, 0]);

    expect(DB::table('grade_scores')->sole()->points)->toBe(5)
        ->and(DB::table('grade_changes')->sole()->grade_correction_id)->toBe($correction);
});

test('a correction allows grading in a closed period', function () {
    $this->travelTo('2027-06-15 10:00:00');
    DB::table('periodos_academicos')->where('id', $this->period)->update(['is_active' => false]);
    correctionGrade($this)->assertStatus(422)->assertExactJson(['message' => 'El periodo está cerrado; sus notas son de solo lectura.']);

    G::openCorrection($this, $this->plan, '2027-06-20');

    correctionGrade($this)->assertOk();
    expect(DB::table('grade_changes')->sole()->grade_correction_id)->not->toBeNull();
});

test('grading is refused again once the correction expires', function () {
    G::openCorrection($this, $this->plan, '2026-10-05');

    $this->travelTo('2026-10-05 23:59:00');
    correctionGrade($this, points: 5)->assertOk();

    $this->travelTo('2026-10-06 00:00:01');
    correctionGrade($this, points: 6)->assertStatus(422)->assertExactJson(['message' => 'La carga de notas de este momento está cerrada.']);

    expect(DB::table('grade_scores')->sole()->points)->toBe(5);
});

test('grading is refused again once the correction is closed', function () {
    G::openCorrection($this, $this->plan, '2026-10-05');
    correctionGrade($this, points: 5)->assertOk();

    $this->actingAs($this->staff)->delete(correctionUrl($this))->assertSessionHasNoErrors();

    correctionGrade($this, points: 6)->assertStatus(422);
    expect(DB::table('grade_scores')->sole()->points)->toBe(5);
});

test('a correction does not lift the subject assignment or the enrollment checks', function () {
    G::openCorrection($this, $this->plan, '2026-10-05');

    correctionGrade($this, user: $this->otherTeacher)
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes asignada esta asignatura en esta sección.']);
    correctionGrade($this, studentId: $this->s3->id)
        ->assertStatus(422)
        ->assertExactJson(['message' => 'El estudiante no está inscrito en esta sección.']);

    expect(DB::table('grade_scores')->count())->toBe(0)
        ->and(DB::table('grade_changes')->count())->toBe(0);
});

test('the sheet shows the open correction and lets the teacher edit outside the window', function () {
    $this->actingAs($this->teacher)->get(G::url($this->school, "/sheets/{$this->plan}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sheet.correction', null)
            ->where('sheet.windowOpen', false)
            ->where('sheet.canEdit', false));

    G::openCorrection($this, $this->plan, '2026-10-05', 'Nota mal cargada');

    $this->actingAs($this->teacher)->get(G::url($this->school, "/sheets/{$this->plan}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sheet.correction', [
                'reason' => 'Nota mal cargada',
                'expiresAt' => Carbon::parse('2026-10-05')->endOfDay()->toIso8601String(),
                'openedBy' => $this->staff->name,
            ])
            ->where('sheet.isStaff', false)
            ->where('sheet.windowOpen', false)
            ->where('sheet.canEdit', true));

    $this->actingAs($this->staff)->get(G::url($this->school, "/sheets/{$this->plan}"))
        ->assertInertia(fn (Assert $page) => $page->where('sheet.isStaff', true));
});

test('the sheet flags the cells changed after their first entry', function () {
    G::openCorrection($this, $this->plan, '2026-10-05');
    [$r1a, $r1b] = $this->indicators;
    $cell = fn (?int $indicator, int $points, int $student) => $this->actingAs($this->teacher)
        ->putJson(G::url($this->school, "/sheets/{$this->plan}/cells"), ['student_id' => $student, 'indicator_id' => $indicator, 'points' => $points])
        ->assertOk();

    $cell($r1a, 5, $this->s1->id);
    $cell($r1a, 9, $this->s1->id);
    $cell($r1b, 3, $this->s1->id);
    $cell(null, 1, $this->s2->id);
    $cell(null, 2, $this->s2->id);

    $this->actingAs($this->teacher)->get(G::url($this->school, "/sheets/{$this->plan}"))
        ->assertInertia(fn (Assert $page) => $page->where('sheet.edited', fn ($edited) => collect($edited)->sort()->values()->all()
            === collect(["{$this->s1->id}:i{$r1a}", "{$this->s2->id}:extra"])->sort()->values()->all()));
});
