<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Grades\GradesFixtures as G;
use Tests\Feature\Subjects\SubjectsFixtures as S;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);
});

function yearUrl(object $test, array $query = []): string
{
    return G::url($test->school, '/year').'?'.http_build_query($query + [
        'period' => $test->period, 'offer' => $test->offer, 'subject' => $test->math,
    ]);
}

/**
 * Loads a student's four indicators of a plan; closed and undated moments
 * need an open correction first.
 *
 * @param  list<int>  $points
 */
function yearCells(object $test, int $plan, int $studentId, array $points): void
{
    foreach (G::indicatorIds($plan) as $i => $indicator) {
        if (! array_key_exists($i, $points)) {
            continue;
        }

        $test->actingAs($test->staff)->putJson(G::url($test->school, "/sheets/{$plan}/cells"), [
            'student_id' => $studentId, 'indicator_id' => $indicator, 'points' => $points[$i],
        ])->assertOk();
    }
}

test('the year summary shows each moment grade and the definitive grade only when every moment is complete', function () {
    $first = G::plan($this, $this->math, $this->moment);
    $second = G::plan($this, $this->math, $this->closedMoment);
    $third = G::plan($this, $this->math, $this->undatedMoment);
    G::openCorrection($this, $second, '2026-10-05');
    G::openCorrection($this, $third, '2026-10-05');

    // s1: 15 (8+7 / 10+5 → 15), 16 (8+8 / 8+8), 16 (8+8 / 9+7) → 15.67 → 16.
    yearCells($this, $first, $this->s1->id, [8, 7, 10, 5]);
    yearCells($this, $second, $this->s1->id, [8, 8, 8, 8]);
    yearCells($this, $third, $this->s1->id, [8, 8, 9, 7]);

    // s2: two complete moments and a partial third → no definitive grade.
    yearCells($this, $first, $this->s2->id, [4, 4, 5, 4]);
    yearCells($this, $second, $this->s2->id, [5, 4, 5, 5]);
    yearCells($this, $third, $this->s2->id, [5]);

    $this->actingAs($this->teacher)->get(yearUrl($this))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades::Year')
            ->where('context.subjectName', 'Matemática')
            ->where('summary.moments', [
                ['id' => $this->moment, 'name' => 'Primer momento', 'planId' => $first],
                ['id' => $this->closedMoment, 'name' => 'Segundo momento', 'planId' => $second],
                ['id' => $this->undatedMoment, 'name' => 'Tercer momento', 'planId' => $third],
            ])
            ->where('summary.rows', function ($rows) {
                $byStudent = collect($rows)->keyBy('id');

                return $byStudent[$this->s1->id]['year'] === 16
                    && $byStudent[$this->s1->id]['passes'] === true
                    && $byStudent[$this->s2->id]['year'] === null
                    && $byStudent[$this->s2->id]['moments'][2]['complete'] === false
                    && $byStudent[$this->s2->id]['moments'][0] === ['final' => 9, 'complete' => true];
            }));
});

test('a moment without a plan leaves every student without a definitive grade', function () {
    $first = G::plan($this, $this->math, $this->moment);
    yearCells($this, $first, $this->s1->id, [12, 8, 10, 10]);

    $this->actingAs($this->staff)->get(yearUrl($this))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.moments.1.planId', null)
            ->where('summary.rows', fn ($rows) => collect($rows)->every(fn ($row) => $row['year'] === null)));
});

test('a period without moments has no definitive grades, not an error', function () {
    DB::table('momentos_academicos')->where('periodo_academico_id', $this->period)->delete();

    $this->actingAs($this->staff)->get(yearUrl($this))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.moments', [])
            ->where('summary.rows', fn ($rows) => collect($rows)->every(fn ($row) => $row['year'] === null && $row['moments'] === [])));
});

test('a teacher who does not teach the subject gets 403', function () {
    $this->actingAs($this->otherTeacher)->get(yearUrl($this))->assertForbidden();
    $this->actingAs($this->teacher)->get(yearUrl($this, ['subject' => $this->art]))->assertForbidden();
});

test('an offer, subject or period that is not the school\'s gets 404', function () {
    $foreignPeriod = S::period($this->otherSchool, '2026', true);

    $this->actingAs($this->staff)->get(yearUrl($this, ['period' => $foreignPeriod]))->assertNotFound();
    $this->actingAs($this->staff)->get(yearUrl($this, ['offer' => 999999]))->assertNotFound();
    $this->actingAs($this->staff)->get(yearUrl($this, ['subject' => 999999]))->assertNotFound();
});

test('a student cannot see the year summary', function () {
    $this->actingAs($this->student)->get(yearUrl($this))->assertForbidden();
});
