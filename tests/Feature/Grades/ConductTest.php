<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Grades\GradesFixtures as G;
use Tests\Feature\Subjects\SubjectsFixtures as S;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);
    // `teacher` is the offer's orientador; `otherTeacher` is not.
    DB::table('ofertas_academicas')->where('id', $this->offer)->update(['teacher_id' => $this->teacher->id]);
});

function conductUrl(object $test, array $query = []): string
{
    return G::url($test->school, '/conduct').'?'.http_build_query($query + ['period' => $test->period, 'offer' => $test->offer]);
}

function recordConduct(object $test, object $user, int $studentId, ?string $letter, ?int $momentId = null)
{
    return $test->actingAs($user)->putJson(G::url($test->school, '/conduct/cells'), [
        'offer_id' => $test->offer,
        'moment_id' => $momentId ?? $test->moment,
        'student_id' => $studentId,
        'letter' => $letter,
    ]);
}

test('the orientador records, changes and clears a letter, and every change is logged', function () {
    recordConduct($this, $this->teacher, $this->s1->id, 'B')->assertOk()->assertJson(['letter' => 'B', 'edited' => false]);
    recordConduct($this, $this->teacher, $this->s1->id, 'A')->assertOk()->assertJson(['letter' => 'A', 'edited' => true]);
    recordConduct($this, $this->teacher, $this->s2->id, 'C')->assertOk()->assertJson(['edited' => false]);
    recordConduct($this, $this->teacher, $this->s2->id, null)->assertOk()->assertJson(['letter' => null, 'edited' => true]);
    // Clearing an empty cell changes nothing and logs nothing.
    recordConduct($this, $this->teacher, $this->s2->id, null)->assertOk();

    expect(DB::table('grade_conduct')->where('student_id', $this->s1->id)->value('letter'))->toBe('A')
        ->and(DB::table('grade_conduct')->where('student_id', $this->s2->id)->exists())->toBeFalse()
        ->and(DB::table('grade_conduct_changes')->orderBy('id')->get(['old_letter', 'new_letter'])->map(fn ($r) => [$r->old_letter, $r->new_letter])->all())
        ->toBe([[null, 'B'], ['B', 'A'], [null, 'C'], ['C', null]]);

    $this->actingAs($this->teacher)->get(conductUrl($this))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades::Conduct')
            ->where('moment.id', $this->moment)
            ->where('canEdit', true)
            ->where('scale.0', ['letter' => 'A', 'label' => 'Excelente'])
            ->has('scale', 5)
            ->where('rows', fn ($rows) => collect($rows)->pluck('letter', 'id')->all() == [$this->s1->id => 'A', $this->s2->id => null])
            ->where('edited', fn ($edited) => collect($edited)->sort()->values()->all() === collect([$this->s1->id, $this->s2->id])->sort()->values()->all()));
});

test('a letter outside the scale is refused', function () {
    recordConduct($this, $this->teacher, $this->s1->id, 'F')
        ->assertStatus(422)
        ->assertJson(['message' => 'La calificación de Convivir debe ser una de: A, B, C, D, E.']);
});

test('only the orientador or staff may record Convivir', function () {
    recordConduct($this, $this->otherTeacher, $this->s1->id, 'A')->assertForbidden();
    $this->actingAs($this->otherTeacher)->get(conductUrl($this))->assertForbidden();
    recordConduct($this, $this->staff, $this->s1->id, 'A')->assertOk();
});

test('the orientador works inside the grading window; staff may record at any time', function () {
    recordConduct($this, $this->teacher, $this->s1->id, 'A', $this->closedMoment)
        ->assertStatus(422)
        ->assertJson(['message' => 'La carga de notas de este momento está cerrada.']);

    recordConduct($this, $this->staff, $this->s1->id, 'A', $this->closedMoment)->assertOk();

    $this->actingAs($this->teacher)->get(conductUrl($this, ['moment' => $this->closedMoment]))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
});

test('a student who is not enrolled in the offer cannot be graded', function () {
    recordConduct($this, $this->teacher, $this->s3->id, 'A')->assertStatus(422);
});

test('another school\'s offer or moment is 404', function () {
    $foreignPeriod = S::period($this->otherSchool, '2026', true);

    $this->actingAs($this->staff)->get(conductUrl($this, ['period' => $foreignPeriod]))->assertNotFound();
    $this->actingAs($this->staff)->get(conductUrl($this, ['offer' => 999999]))->assertNotFound();
    recordConduct($this, $this->staff, $this->s1->id, 'A', 999999)->assertStatus(422);
});

test('a period without moments shows an empty Convivir page, not an error', function () {
    DB::table('momentos_academicos')->where('periodo_academico_id', $this->period)->delete();

    $this->actingAs($this->teacher)->get(conductUrl($this))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('moments', [])
            ->where('moment', null)
            ->where('canEdit', false)
            ->where('edited', []));
});

test('the index lists the orientador\'s sections', function () {
    $this->actingAs($this->teacher)->get(G::url($this->school))
        ->assertInertia(fn (Assert $page) => $page->where('homerooms', [['id' => $this->offer, 'label' => 'Primer año · Sección A']]));

    $this->actingAs($this->otherTeacher)->get(G::url($this->school))
        ->assertInertia(fn (Assert $page) => $page->where('homerooms', []));
});
