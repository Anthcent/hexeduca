<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Grades\GradesFixtures as G;
use Tests\Feature\Subjects\SubjectsFixtures as S;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);
});

function resultsUrl(object $test, array $query = []): string
{
    return G::url($test->school, '/results').'?'.http_build_query($query + ['period' => $test->period]);
}

/**
 * Plans a subject in the three moments (with corrections open on the
 * closed and undated ones) and loads every indicator of each student with
 * the same points.
 *
 * @param  array<int, int>  $pointsByStudent  student id => points per indicator
 */
function wholeYear(object $test, int $subjectId, array $pointsByStudent): void
{
    foreach ([$test->moment, $test->closedMoment, $test->undatedMoment] as $momentId) {
        $plan = G::plan($test, $subjectId, $momentId);

        if ($momentId !== $test->moment) {
            G::openCorrection($test, $plan, '2026-10-05');
        }

        foreach ($pointsByStudent as $studentId => $points) {
            foreach (G::indicatorIds($plan) as $indicator) {
                $test->actingAs($test->staff)->putJson(G::url($test->school, "/sheets/{$plan}/cells"), [
                    'student_id' => $studentId, 'indicator_id' => $indicator, 'points' => $points,
                ])->assertOk();
            }
        }
    }
}

test('staff sees each student\'s definitive grades and the result of the year', function () {
    $s4 = S::user($this->school, 'student');
    G::enroll($this->school, $this->period, $this->offer, $s4->id);

    // 8 points in each of the four indicators: referentes of 16, so 16 a moment.
    wholeYear($this, $this->art, [$this->s1->id => 8, $this->s2->id => 8]);
    // s2 scores 0 everywhere in Matemática: 01 a moment, 01 for the year.
    wholeYear($this, $this->math, [$this->s1->id => 8, $this->s2->id => 0]);

    $this->actingAs($this->staff)->get(resultsUrl($this))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades::Results')
            ->where('offerId', $this->offer)
            ->where('subjects', fn ($subjects) => collect($subjects)->pluck('name')->all() === ['Arte', 'Matemática'])
            ->where('rows', function ($rows) use ($s4) {
                $byStudent = collect($rows)->keyBy('id');

                return $byStudent[$this->s1->id]['years'] == [$this->art => 16, $this->math => 16]
                    && $byStudent[$this->s1->id]['outcome'] === 'passed'
                    && $byStudent[$this->s2->id]['years'] == [$this->art => 16, $this->math => 1]
                    && $byStudent[$this->s2->id]['failed'] === 1
                    && $byStudent[$this->s2->id]['outcome'] === 'pending'
                    && $byStudent[$s4->id]['outcome'] === 'incomplete';
            }));
});

test('a teacher cannot see the year results', function () {
    $this->actingAs($this->teacher)->get(resultsUrl($this))->assertForbidden();
});

test('another school\'s period or offer gets 404', function () {
    $foreignPeriod = S::period($this->otherSchool, '2026', true);

    $this->actingAs($this->staff)->get(resultsUrl($this, ['period' => $foreignPeriod]))->assertNotFound();
    $this->actingAs($this->staff)->get(resultsUrl($this, ['offer' => 999999]))->assertNotFound();
});
