<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Grades\GradesFixtures as G;
use Tests\Feature\Subjects\SubjectsFixtures as S;
use Tests\Feature\TeachingAssignments\TeachingAssignmentsFixtures as T;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);
});

function monitorCell(object $test, int $plan, int $studentId, int $indicatorId, int $points): void
{
    $test->actingAs($test->staff)->putJson(G::url($test->school, "/sheets/{$plan}/cells"), [
        'student_id' => $studentId, 'indicator_id' => $indicatorId, 'points' => $points,
    ])->assertOk();
}

test('a teacher cannot see the monitor', function () {
    $this->actingAs($this->teacher)->get(G::url($this->school, '/monitor'))->assertForbidden();
});

test('without an active period the monitor is empty, not an error', function () {
    DB::table('periodos_academicos')->where('id', $this->period)->update(['is_active' => false]);

    $this->actingAs($this->staff)->get(G::url($this->school, '/monitor'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades::Monitor')
            ->where('period', null)
            ->where('moments', [])
            ->where('moment', null)
            ->where('rows', []));
});

test('the monitor lists every subject of every offer with its titular, progress and status', function () {
    $offerB = S::offer($this->school, $this->period, $this->grade1, S::section($this->school, 'B'));
    $sB = S::user($this->school, 'student');
    G::enroll($this->school, $this->period, $offerB, $sB->id);
    // Only the titular is shown: a substitute on Arte leaves it without a teacher.
    T::assignment($this->school, $this->period, $this->offer, $this->art, $this->otherTeacher->id, 'suplente');

    // A · Matemática: complete, s1 passes with 20 and s2 fails with 01.
    $mathA = G::plan($this, $this->math, $this->moment);
    foreach (G::indicatorIds($mathA) as $i => $indicator) {
        monitorCell($this, $mathA, $this->s1->id, $indicator, [12, 8, 10, 10][$i]);
        monitorCell($this, $mathA, $this->s2->id, $indicator, 0);
    }

    // B · Arte: planned, nothing loaded, under correction.
    $artB = G::plan($this, $this->art, $this->moment, $offerB);
    G::openCorrection($this, $artB, '2026-10-05');

    // B · Matemática: 1 of 4 scores; a partial final (03) is not a result yet, so it is not failing.
    $mathB = G::plan($this, $this->math, $this->moment, $offerB);
    monitorCell($this, $mathB, $sB->id, G::indicatorIds($mathB)[0], 5);

    $this->actingAs($this->staff)->get(G::url($this->school, '/monitor'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades::Monitor')
            ->where('period.id', $this->period)
            ->where('moment.id', $this->moment)
            ->where('moment.windowOpen', true)
            ->has('moments', 3)
            ->where('rows', function ($rows) use ($mathA, $artB, $mathB) {
                $pick = fn ($row) => [$row['offerLabel'], $row['subjectName'], $row['teacher'], $row['planId'], $row['students'], $row['complete'], $row['failing'], $row['progress'], $row['status'], $row['correctionUntil']];

                return collect($rows)->map($pick)->all() === [
                    ['Primer año · Sección A', 'Arte', null, null, 2, 0, 0, 0, 'no_plan', null],
                    ['Primer año · Sección A', 'Matemática', $this->teacher->name, $mathA, 2, 2, 1, 100, 'complete', null],
                    ['Primer año · Sección B', 'Arte', null, $artB, 1, 0, 0, 0, 'not_started', '2026-10-05T23:59:59+00:00'],
                    ['Primer año · Sección B', 'Matemática', null, $mathB, 1, 0, 0, 25, 'in_progress', null],
                ];
            }));
});

test('an expired correction is not shown on the monitor', function () {
    $plan = G::plan($this, $this->math, $this->moment);
    G::openCorrection($this, $plan, '2026-09-28');

    $this->travelTo('2026-09-29 08:00:00');

    $this->actingAs($this->staff)->get(G::url($this->school, '/monitor'))
        ->assertInertia(fn (Assert $page) => $page->where('rows.1.planId', $plan)->where('rows.1.correctionUntil', null));
});

test('the monitor defaults to the open moment and ?moment= selects another', function () {
    G::plan($this, $this->math, $this->moment);

    $this->actingAs($this->staff)->get(G::url($this->school, "/monitor?moment={$this->closedMoment}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('moment.id', $this->closedMoment)
            ->where('moment.windowOpen', false)
            ->where('rows', fn ($rows) => collect($rows)->pluck('status')->unique()->values()->all() === ['no_plan']));

    // With no window open it falls back to the last moment.
    $this->travelTo('2026-10-20 10:00:00');
    $this->actingAs($this->staff)->get(G::url($this->school, '/monitor'))
        ->assertInertia(fn (Assert $page) => $page->where('moment.id', $this->undatedMoment));
});
