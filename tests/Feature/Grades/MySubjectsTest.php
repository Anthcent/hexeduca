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

test('a teacher sees only the subjects they are assigned to', function () {
    // An ended assignment and another period's assignment do not count.
    T::assignment($this->school, $this->period, $this->offer, $this->art, $this->teacher->id, 'titular', '2026-09-20');
    $next = S::period($this->school, '2027', false, '2027-01-01', '2027-12-31');
    $nextOffer = S::offer($this->school, $next, $this->grade1, S::section($this->school, 'B'));
    T::assignment($this->school, $next, $nextOffer, $this->math, $this->teacher->id);

    $this->actingAs($this->teacher)->get(G::url($this->school))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades::Index')
            ->where('period.id', $this->period)
            ->where('seesAllSections', false)
            ->where('can', ['monitor' => false, 'results' => false])
            ->has('cards', 1)
            ->where('cards.0.subjectName', 'Matemática')
            ->where('cards.0.offerLabel', 'Primer año · Sección A')
            ->where('cards.0.role', 'titular')
            ->where('cards.0.students', 2)
            ->has('cards.0.moments', 3)
            ->where('cards.0.moments', fn ($moments) => collect($moments)->pluck('window')->all() === ['open', 'closed', 'undefined']));

    $this->actingAs($this->otherTeacher)->get(G::url($this->school))
        ->assertInertia(fn (Assert $page) => $page->where('cards', []));
});

test('staff sees every subject of every offer, with the plan and loading progress per moment', function () {
    $this->actingAs($this->staff)
        ->put(G::url($this->school, '/plan'), G::planPayload($this->offer, $this->math, $this->moment))
        ->assertSessionHasNoErrors();
    $plan = (int) DB::table('grade_plans')->value('id');
    $this->actingAs($this->staff)->putJson(G::url($this->school, "/sheets/{$plan}/cells"), [
        'student_id' => $this->s1->id, 'indicator_id' => G::indicatorIds($plan)[0], 'points' => 5,
    ])->assertOk();

    $this->actingAs($this->staff)->get(G::url($this->school))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seesAllSections', true)
            ->where('can', ['monitor' => true, 'results' => true])
            ->where('cards', function ($cards) use ($plan) {
                $cards = collect($cards);
                $math = $cards->firstWhere('subjectName', 'Matemática');

                return $cards->pluck('subjectName')->all() === ['Arte', 'Matemática']
                    && $math['role'] === null
                    && $math['moments'][0]['planId'] === $plan
                    // 1 score of 2 students × 4 indicators.
                    && $math['moments'][0]['progress'] === 12
                    && $math['moments'][1]['planId'] === null;
            }));
});

test('a moment flags its plan\'s open correction', function () {
    $plan = G::plan($this, $this->math, $this->closedMoment);
    $flags = function () {
        $moments = null;
        $this->actingAs($this->teacher)->get(G::url($this->school))
            ->assertInertia(function (Assert $page) use (&$moments) {
                $moments = $page->toArray()['props']['cards'][0]['moments'];
            });

        return $moments;
    };

    expect(collect($flags())->pluck('correction')->all())->toBe([false, false, false]);

    G::openCorrection($this, $plan, '2026-10-05');
    expect(collect($flags())->pluck('correction')->all())->toBe([false, true, false]);

    $this->actingAs($this->staff)->delete(G::url($this->school, "/sheets/{$plan}/correction"))->assertSessionHasNoErrors();
    expect(collect($flags())->pluck('correction')->all())->toBe([false, false, false]);
});

test('the moment windows report upcoming dates', function () {
    DB::table('momentos_academicos')->where('id', $this->undatedMoment)->update(['grading_opens_on' => '2026-11-01', 'grading_closes_on' => '2026-11-15']);

    $this->actingAs($this->teacher)->get(G::url($this->school))
        ->assertInertia(fn (Assert $page) => $page->where('cards.0.moments.2.window', 'upcoming'));
});
