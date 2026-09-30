<?php

use App\ModulePlatform\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AcademicMoments\AcademicMomentsFixtures as M;
use Tests\Feature\Grades\GradesFixtures as G;
use Tests\Feature\Subjects\SubjectsFixtures as S;
use Tests\Feature\TeachingAssignments\TeachingAssignmentsFixtures as T;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);
});

/**
 * Staff saves a plan for the slot and returns its id.
 */
function gradesAccessPlan(object $test, int $subjectId): int
{
    $test->actingAs($test->staff)
        ->put(G::url($test->school, '/plan'), G::planPayload($test->offer, $subjectId, $test->moment))
        ->assertSessionHasNoErrors();

    return (int) DB::table('grade_plans')->where('study_plan_subject_id', $subjectId)->value('id');
}

test('a student cannot use the grades module', function () {
    $plan = gradesAccessPlan($this, $this->math);
    $indicator = G::indicatorIds($plan)[0];

    $this->actingAs($this->student)->get(G::url($this->school))->assertForbidden();
    $this->actingAs($this->student)->get(G::url($this->school, "/plan?offer={$this->offer}&subject={$this->math}&moment={$this->moment}"))->assertForbidden();
    $this->actingAs($this->student)->put(G::url($this->school, '/plan'), G::planPayload($this->offer, $this->math, $this->moment))->assertForbidden();
    $this->actingAs($this->student)->get(G::url($this->school, "/sheets/{$plan}"))->assertForbidden();
    $this->actingAs($this->student)->putJson(G::url($this->school, "/sheets/{$plan}/cells"), [
        'student_id' => $this->s1->id, 'indicator_id' => $indicator, 'points' => 5,
    ])->assertForbidden();

    expect(DB::table('grade_scores')->count())->toBe(0);
});

test('the assigned teacher works on their subject', function () {
    $this->actingAs($this->teacher)->get(G::url($this->school, "/plan?offer={$this->offer}&subject={$this->math}&moment={$this->moment}"))
        ->assertOk();
    $this->actingAs($this->teacher)->put(G::url($this->school, '/plan'), G::planPayload($this->offer, $this->math, $this->moment))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Plan de evaluación guardado.');

    $plan = (int) DB::table('grade_plans')->sole()->id;

    $this->actingAs($this->teacher)->get(G::url($this->school, "/sheets/{$plan}"))->assertOk();
    $this->actingAs($this->teacher)->putJson(G::url($this->school, "/sheets/{$plan}/cells"), [
        'student_id' => $this->s1->id, 'indicator_id' => G::indicatorIds($plan)[0], 'points' => 5,
    ])->assertOk();

    expect(DB::table('grade_plans')->sole()->created_by)->toBe($this->teacher->id);
});

test('a teacher gets 403 on a subject they are not assigned to', function () {
    $artPlan = gradesAccessPlan($this, $this->art);
    $mathPlan = gradesAccessPlan($this, $this->math);

    foreach ([[$this->teacher, $this->art, $artPlan], [$this->otherTeacher, $this->math, $mathPlan]] as [$user, $subject, $plan]) {
        $this->actingAs($user)->get(G::url($this->school, "/plan?offer={$this->offer}&subject={$subject}&moment={$this->moment}"))->assertForbidden();
        $this->actingAs($user)->put(G::url($this->school, '/plan'), G::planPayload($this->offer, $subject, $this->closedMoment))->assertForbidden();
        $this->actingAs($user)->get(G::url($this->school, "/sheets/{$plan}"))->assertForbidden();
        $this->actingAs($user)->putJson(G::url($this->school, "/sheets/{$plan}/cells"), [
            'student_id' => $this->s1->id, 'indicator_id' => G::indicatorIds($plan)[0], 'points' => 5,
        ])->assertForbidden()->assertJson(['message' => 'No tienes asignada esta asignatura en esta sección.']);
    }

    expect(DB::table('grade_plans')->count())->toBe(2)
        ->and(DB::table('grade_scores')->count())->toBe(0);
});

test('a substitute may act, but an ended assignment gives no access', function () {
    $plan = gradesAccessPlan($this, $this->art);
    $substitute = S::user($this->school, 'teacher');
    $former = S::user($this->school, 'teacher');
    T::assignment($this->school, $this->period, $this->offer, $this->art, $substitute->id, 'suplente');
    T::assignment($this->school, $this->period, $this->offer, $this->art, $former->id, 'titular', '2026-09-20');

    $this->actingAs($substitute)->get(G::url($this->school, "/sheets/{$plan}"))->assertOk();
    $this->actingAs($former)->get(G::url($this->school, "/sheets/{$plan}"))->assertForbidden();
});

test('a school not entitled to the module gets 404, whatever the role', function () {
    $plan = gradesAccessPlan($this, $this->math);
    app(ModuleRegistry::class)->revoke('grades', $this->school);

    $this->actingAs($this->staff)->get(G::url($this->school))->assertNotFound();
    $this->actingAs($this->staff)->get(G::url($this->school, "/sheets/{$plan}"))->assertNotFound();
    $this->actingAs($this->teacher)->putJson(G::url($this->school, "/sheets/{$plan}/cells"), [
        'student_id' => $this->s1->id, 'indicator_id' => G::indicatorIds($plan)[0], 'points' => 5,
    ])->assertNotFound();
    $this->actingAs($this->student)->get(G::url($this->school))->assertNotFound();
});

test('another school\'s plan is 404 and its offer or moment cannot be planned', function () {
    $foreignPeriod = S::period($this->otherSchool, '2026', true);
    $foreignGrade = S::gradeLevel($this->otherSchool, 'Primero', 1);
    $foreignOffer = S::offer($this->otherSchool, $foreignPeriod, $foreignGrade, S::section($this->otherSchool, 'A'));
    $foreignStudyPlan = S::plan($this->otherSchool, '1');
    $foreignSubject = S::subject($this->otherSchool, $foreignStudyPlan, $foreignGrade, 'Física');
    S::assignment($this->otherSchool, $foreignPeriod, $foreignStudyPlan, 'school');
    $foreignMoment = M::moment($foreignPeriod, 'Primer momento', 1, '2026-09-01', '2026-10-15');
    $foreignStaff = S::user($this->otherSchool, 'staff/admin');

    $this->actingAs($foreignStaff)
        ->put(G::url($this->otherSchool, '/plan'), G::planPayload($foreignOffer, $foreignSubject, $foreignMoment))
        ->assertSessionHasNoErrors();
    $foreignPlan = (int) DB::table('grade_plans')->sole()->id;

    $this->actingAs($this->staff)->get(G::url($this->school, "/sheets/{$foreignPlan}"))->assertNotFound();
    $this->actingAs($this->staff)->putJson(G::url($this->school, "/sheets/{$foreignPlan}/cells"), [
        'student_id' => $this->s1->id, 'indicator_id' => G::indicatorIds($foreignPlan)[0], 'points' => 5,
    ])->assertNotFound();
    $this->actingAs($this->staff)->get(G::url($this->school, "/plan?offer={$foreignOffer}&subject={$foreignSubject}&moment={$foreignMoment}"))->assertNotFound();

    // Mixing a foreign moment with the school's own offer is refused too.
    $this->actingAs($this->staff)->put(G::url($this->school, '/plan'), G::planPayload($this->offer, $this->math, $foreignMoment))
        ->assertSessionHas('error', 'La sección, la asignatura o el momento no son válidos para este periodo.');
    $this->actingAs($this->staff)->put(G::url($this->school, '/plan'), G::planPayload($foreignOffer, $foreignSubject, $this->moment))
        ->assertSessionHas('error', 'La sección, la asignatura o el momento no son válidos para este periodo.');

    expect(DB::table('grade_plans')->count())->toBe(1)
        ->and(DB::table('grade_scores')->count())->toBe(0);
});

test('a subject that is not in force for the offer cannot be planned', function () {
    $archived = S::subject($this->school, $this->studyPlan, $this->grade1, 'Latín', ['status' => 'archived']);

    $this->actingAs($this->staff)->get(G::url($this->school, "/plan?offer={$this->offer}&subject={$archived}&moment={$this->moment}"))->assertNotFound();
    $this->actingAs($this->staff)->put(G::url($this->school, '/plan'), G::planPayload($this->offer, $archived, $this->moment))
        ->assertSessionHas('error', 'La sección, la asignatura o el momento no son válidos para este periodo.');

    expect(DB::table('grade_plans')->count())->toBe(0);
});
