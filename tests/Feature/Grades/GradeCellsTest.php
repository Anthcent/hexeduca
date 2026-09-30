<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Grades\GradesFixtures as G;
use Tests\Feature\Subjects\SubjectsFixtures as S;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);

    // Referentes 12 + 8 and 10 + 10.
    $this->actingAs($this->staff)
        ->put(G::url($this->school, '/plan'), G::planPayload($this->offer, $this->math, $this->moment))
        ->assertSessionHasNoErrors();
    $this->plan = (int) DB::table('grade_plans')->value('id');
    $this->indicators = G::indicatorIds($this->plan);
});

function gradeCell(object $test, ?int $indicatorId, ?int $points, ?int $studentId = null, ?int $plan = null)
{
    return $test->actingAs($test->teacher)->putJson(G::url($test->school, '/sheets/'.($plan ?? $test->plan).'/cells'), [
        'student_id' => $studentId ?? $test->s1->id,
        'indicator_id' => $indicatorId,
        'points' => $points,
    ]);
}

/**
 * A plan on another moment of the same slot, saved by staff.
 */
function gradeCellsPlanOn(object $test, int $momentId): int
{
    $test->actingAs($test->staff)
        ->put(G::url($test->school, '/plan'), G::planPayload($test->offer, $test->math, $momentId))
        ->assertSessionHasNoErrors();

    return (int) DB::table('grade_plans')->where('academic_moment_id', $momentId)->value('id');
}

test('recording scores returns the recomputed row: 17 + 16 averages 16.5 and rounds to 17', function () {
    [$r1a, $r1b, $r2a, $r2b] = $this->indicators;

    gradeCell($this, $r1a, 10)->assertOk()->assertJsonPath('row.referentTotals', [10, 0])->assertJsonPath('row.complete', false);
    gradeCell($this, $r1b, 7)->assertOk();
    gradeCell($this, $r2a, 8)->assertOk();

    gradeCell($this, $r2b, 8)
        ->assertOk()
        ->assertExactJson(['row' => [
            'referentTotals' => [17, 16],
            'average' => 16.5,
            'extra' => 0,
            'maxExtra' => 3,
            'final' => 17,
            'passes' => true,
            'complete' => true,
        ]]);

    $result = DB::table('grade_results')->sole();
    expect([$result->student_id, (float) $result->average, $result->extra, $result->final, (bool) $result->complete])
        ->toBe([$this->s1->id, 16.5, 0, 17, true]);
});

test('9.5 rounds to 10 and passes', function () {
    [$r1a, , $r2a] = $this->indicators;

    gradeCell($this, $r1a, 10)->assertOk();
    gradeCell($this, $r2a, 9)->assertOk()->assertJsonPath('row.average', 9.5)->assertJsonPath('row.final', 10)->assertJsonPath('row.passes', true);
});

test('a student with only zeros gets 01', function () {
    foreach ($this->indicators as $indicator) {
        gradeCell($this, $indicator, 0)->assertOk();
    }

    expect(DB::table('grade_results')->sole()->final)->toBe(1);
});

test('a score outside the indicator range is refused with a message', function (int $points, string $message) {
    gradeCell($this, $this->indicators[0], $points)->assertStatus(422)->assertExactJson(['message' => $message]);

    expect(DB::table('grade_scores')->count())->toBe(0)
        ->and(DB::table('grade_changes')->count())->toBe(0)
        ->and(DB::table('grade_results')->count())->toBe(0);
})->with([
    'over the indicator maximum' => [13, 'La nota debe ser un número entero entre 0 y 12.'],
    'over 20' => [21, 'La nota debe ser un número entero entre 0 y 20.'],
    'negative' => [-1, 'La nota debe ser un número entero entre 0 y 20.'],
]);

test('grades are refused outside the moment\'s grading window, or when it has no dates', function () {
    foreach ([$this->closedMoment, $this->undatedMoment] as $moment) {
        $plan = gradeCellsPlanOn($this, $moment);

        gradeCell($this, G::indicatorIds($plan)[0], 5, plan: $plan)
            ->assertStatus(422)
            ->assertExactJson(['message' => 'La carga de notas de este momento está cerrada.']);
    }

    expect(DB::table('grade_scores')->count())->toBe(0);
});

test('the window includes its first and last day', function () {
    $this->travelTo('2026-10-15 23:00:00');
    gradeCell($this, $this->indicators[0], 5)->assertOk();

    $this->travelTo('2026-10-16 00:30:00');
    gradeCell($this, $this->indicators[0], 6)->assertStatus(422);

    $this->travelTo('2026-09-01 00:30:00');
    gradeCell($this, $this->indicators[0], 7)->assertOk();

    expect(DB::table('grade_scores')->sole()->points)->toBe(7);
});

test('only students actively enrolled in the offer can be graded', function () {
    $withdrawn = S::user($this->school, 'student');
    G::enroll($this->school, $this->period, $this->offer, $withdrawn->id, 'withdrawn');
    $elsewhere = S::user($this->school, 'student');
    $otherOffer = S::offer($this->school, $this->period, $this->grade1, S::section($this->school, 'B'));
    G::enroll($this->school, $this->period, $otherOffer, $elsewhere->id);

    foreach ([$this->s3, $withdrawn, $elsewhere] as $student) {
        gradeCell($this, $this->indicators[0], 5, $student->id)
            ->assertStatus(422)
            ->assertExactJson(['message' => 'El estudiante no está inscrito en esta sección.']);
    }

    expect(DB::table('grade_scores')->count())->toBe(0);
});

test('an indicator of another plan is refused', function () {
    $artPlanPayload = G::planPayload($this->offer, $this->art, $this->moment);
    $this->actingAs($this->staff)->put(G::url($this->school, '/plan'), $artPlanPayload)->assertSessionHasNoErrors();
    $artPlan = (int) DB::table('grade_plans')->where('study_plan_subject_id', $this->art)->value('id');

    gradeCell($this, G::indicatorIds($artPlan)[0], 5)
        ->assertStatus(422)
        ->assertExactJson(['message' => 'El indicador no pertenece a este plan.']);

    expect(DB::table('grade_scores')->count())->toBe(0);
});

test('the extra may only fill the room up to 20', function () {
    [$r1a, $r1b, $r2a, $r2b] = $this->indicators;
    gradeCell($this, $r1a, 10);
    gradeCell($this, $r1b, 7);
    gradeCell($this, $r2a, 8);
    gradeCell($this, $r2b, 8);

    gradeCell($this, null, 4)
        ->assertStatus(422)
        ->assertExactJson(['message' => 'Con el promedio actual, la nota extra puede ser como máximo 3 para no pasar de 20.']);
    expect(DB::table('grade_extras')->count())->toBe(0);

    gradeCell($this, null, 3)->assertOk()->assertJsonPath('row.extra', 3)->assertJsonPath('row.final', 20)->assertJsonPath('row.maxExtra', 3);
    expect(DB::table('grade_extras')->sole()->points)->toBe(3)
        ->and(DB::table('grade_results')->sole()->final)->toBe(20);
});

test('a student already at 20 has no room for an extra', function () {
    foreach ($this->indicators as $i => $indicator) {
        gradeCell($this, $indicator, [12, 8, 10, 10][$i]);
    }

    gradeCell($this, null, 1)
        ->assertStatus(422)
        ->assertExactJson(['message' => 'El estudiante ya llega a 20: no hay lugar para puntos extra.']);
});

test('null clears a score or the extra and the grade is recomputed', function () {
    [$r1a, , $r2a] = $this->indicators;
    gradeCell($this, $r1a, 12);
    gradeCell($this, $r2a, 10);
    gradeCell($this, null, 2)->assertJsonPath('row.final', 13);

    gradeCell($this, $r2a, null)->assertOk()->assertJsonPath('row.referentTotals', [12, 0])->assertJsonPath('row.final', 8);
    gradeCell($this, null, null)->assertOk()->assertJsonPath('row.extra', 0)->assertJsonPath('row.final', 6);

    expect(DB::table('grade_scores')->pluck('grade_plan_indicator_id')->all())->toBe([$r1a])
        ->and(DB::table('grade_extras')->count())->toBe(0)
        ->and(DB::table('grade_results')->sole()->final)->toBe(6);
});

test('every score or extra change is logged with who, before and after; a repeat writes nothing', function () {
    [$r1a] = $this->indicators;

    gradeCell($this, $r1a, 5);
    gradeCell($this, $r1a, 9);
    gradeCell($this, $r1a, 9);
    gradeCell($this, null, 1);
    gradeCell($this, $r1a, null);
    gradeCell($this, $r1a, 3, $this->s2->id);

    $changes = DB::table('grade_changes')->orderBy('id')->get();
    expect($changes)->toHaveCount(5)
        ->and($changes->map(fn ($c) => [$c->student_id, $c->grade_plan_indicator_id, $c->old_points, $c->new_points])->all())->toBe([
            [$this->s1->id, $r1a, null, 5],
            [$this->s1->id, $r1a, 5, 9],
            [$this->s1->id, null, null, 1],
            [$this->s1->id, $r1a, 9, null],
            [$this->s2->id, $r1a, null, 3],
        ])
        ->and($changes->pluck('changed_by')->unique()->all())->toBe([$this->teacher->id])
        ->and($changes->pluck('school_id')->unique()->all())->toBe([$this->school->id])
        ->and(DB::table('grade_results')->orderBy('student_id')->pluck('final', 'student_id')->all())
        ->toBe([$this->s1->id => 1, $this->s2->id => 2]);
});

test('a closed period is read-only', function () {
    $this->travelTo('2027-06-15 10:00:00');
    DB::table('periodos_academicos')->where('id', $this->period)->update(['is_active' => false]);
    DB::table('momentos_academicos')->where('id', $this->moment)->update(['grading_opens_on' => '2027-06-01', 'grading_closes_on' => '2027-06-30']);

    gradeCell($this, $this->indicators[0], 5)
        ->assertStatus(422)
        ->assertExactJson(['message' => 'El periodo está cerrado; sus notas son de solo lectura.']);
});

test('the sheet lists the enrolled students with their scores and computed grade', function () {
    [$r1a, , $r2a] = $this->indicators;
    gradeCell($this, $r1a, 12);
    gradeCell($this, $r2a, 7);

    $this->actingAs($this->teacher)->get(G::url($this->school, "/sheets/{$this->plan}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades::Sheet')
            ->has('sheet.rows', 2)
            ->where('sheet.windowOpen', true)
            ->where('sheet.canEdit', true)
            ->where('sheet.rows', function ($rows) {
                $rows = collect($rows)->keyBy('id');

                return $rows[$this->s1->id]['final'] === 10
                    && $rows[$this->s1->id]['average'] === 9.5
                    && $rows[$this->s2->id]['final'] === null;
            }));
});
