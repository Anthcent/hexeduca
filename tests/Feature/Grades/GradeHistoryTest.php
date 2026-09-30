<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Grades\GradesFixtures as G;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);

    $this->plan = G::plan($this, $this->math, $this->moment);
    $this->indicators = G::indicatorIds($this->plan);
});

function historyCell(object $test, object $user, int $studentId, ?int $indicatorId, int $points): void
{
    $test->actingAs($user)->putJson(G::url($test->school, "/sheets/{$test->plan}/cells"), [
        'student_id' => $studentId, 'indicator_id' => $indicatorId, 'points' => $points,
    ])->assertOk();
}

function historyUrl(object $test, ?int $plan = null): string
{
    return G::url($test->school, '/sheets/'.($plan ?? $test->plan).'/history');
}

test('the history lists every change newest first, with names, cell labels and the correction', function () {
    [$r1a, , , $r2b] = $this->indicators;

    historyCell($this, $this->teacher, $this->s1->id, $r1a, 5);
    historyCell($this, $this->teacher, $this->s2->id, $r2b, 7);
    G::openCorrection($this, $this->plan, '2026-10-05', 'Nota mal cargada');
    historyCell($this, $this->staff, $this->s1->id, $r1a, 9);
    historyCell($this, $this->teacher, $this->s1->id, null, 1);

    $response = $this->actingAs($this->teacher)->getJson(historyUrl($this))->assertOk()->assertJsonPath('truncated', false);

    $changes = collect($response->json('changes'));
    expect($changes->map(fn ($c) => [$c['student'], $c['cell'], $c['old'], $c['new'], $c['by'], $c['role'], $c['correction']])->all())->toBe([
        [$this->s1->name, 'Extra', null, 1, $this->teacher->name, 'teacher', 'Nota mal cargada'],
        [$this->s1->name, 'R1-A', 5, 9, $this->staff->name, 'staff/admin', 'Nota mal cargada'],
        [$this->s2->name, 'R2-B', null, 7, $this->teacher->name, 'teacher', null],
        [$this->s1->name, 'R1-A', null, 5, $this->teacher->name, 'teacher', null],
    ])
        ->and($changes->pluck('id')->all())->toBe(DB::table('grade_changes')->orderByDesc('id')->pluck('id')->all())
        ->and($changes->first()['at'])->toBe(now()->toIso8601String());
});

test('the history is capped at 500 changes and says when it is truncated', function () {
    $row = fn () => [
        'school_id' => $this->school->id,
        'grade_plan_id' => $this->plan,
        'student_id' => $this->s1->id,
        'grade_plan_indicator_id' => $this->indicators[0],
        'old_points' => 1,
        'new_points' => 2,
        'changed_by' => $this->teacher->id,
        'created_at' => now(),
    ];

    DB::table('grade_changes')->insert(array_map(fn () => $row(), range(1, 500)));
    $this->actingAs($this->staff)->getJson(historyUrl($this))
        ->assertOk()
        ->assertJsonCount(500, 'changes')
        ->assertJsonPath('truncated', false);

    DB::table('grade_changes')->insert($row());
    $newest = (int) DB::table('grade_changes')->max('id');

    $this->actingAs($this->staff)->getJson(historyUrl($this))
        ->assertJsonCount(500, 'changes')
        ->assertJsonPath('truncated', true)
        ->assertJsonPath('changes.0.id', $newest);
});

test('the history follows the sheet access rules', function () {
    $this->actingAs($this->student)->getJson(historyUrl($this))->assertForbidden();
    $this->actingAs($this->otherTeacher)->getJson(historyUrl($this))->assertForbidden();
    $this->actingAs($this->staff)->getJson(historyUrl($this, G::foreignPlan($this)))->assertNotFound();
});
