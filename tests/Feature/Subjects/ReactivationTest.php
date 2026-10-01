<?php

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Subjects\SubjectsFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();

    $this->seed(ModulePlatformSeeder::class);

    $this->staff = F::user($this->school, 'director');
    $this->grade1 = F::gradeLevel($this->school, 'Primer año', 1);
    $this->grade2 = F::gradeLevel($this->school, 'Segundo año', 2);

    $this->period = F::period($this->school, '2026', true);
    $this->pastPeriod = F::period($this->school, '2024', false, '2024-01-01', '2024-11-30');

    $this->archived = F::plan($this->school, '31060', ['status' => 'archived']);
    F::subject($this->school, $this->archived, $this->grade1, 'Matemática');
    $this->other = F::plan($this->school, '40000');
});

test('without conflicts the review shows the restorable free slots and a plain confirmation reactivates', function () {
    $lost = F::assignment($this->school, $this->period, $this->archived, 'grade_level', $this->grade1, replaced: true);
    // Past periods are history: never restored.
    F::assignment($this->school, $this->pastPeriod, $this->archived, 'school', replaced: true);

    $this->actingAs($this->staff)->get(F::url($this->school, "/{$this->archived}/reactivation"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subjects::Reactivation')
            ->where('kind', 'plan')
            ->where('summary.subjectCount', 1)
            ->has('restorations', 1)
            ->where('restorations.0.restored.id', $lost)
            ->where('restorations.0.holder', null)
            ->where('conflictIds', []));

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$this->archived}/reactivate"), ['approved_assignment_ids' => []])
        ->assertRedirect(route('subjects.plans.show', $this->archived))
        ->assertSessionHas('success', 'Plan de estudio reactivado.');

    expect(DB::table('study_plans')->where('id', $this->archived)->value('status'))->toBe('active')
        ->and(DB::table('study_plan_assignments')->where('id', $lost)->value('replaced_at'))->toBeNull()
        ->and(DB::table('study_plan_assignments')->where('academic_period_id', $this->pastPeriod)->value('replaced_at'))->not->toBeNull();
});

test('conflicts are listed and nothing changes unless every one is approved', function () {
    $lostSchool = F::assignment($this->school, $this->period, $this->archived, 'school', replaced: true);
    $lostGrade = F::assignment($this->school, $this->period, $this->archived, 'grade_level', $this->grade2, replaced: true);
    $holderSchool = F::assignment($this->school, $this->period, $this->other, 'school');
    $holderGrade = F::assignment($this->school, $this->period, $this->other, 'grade_level', $this->grade2);
    $keep = F::subject($this->school, $this->other, $this->grade2, 'Química');
    F::exclude($this->school, $holderGrade, $keep);

    $this->actingAs($this->staff)->get(F::url($this->school, "/{$this->archived}/reactivation"))
        ->assertInertia(fn (Assert $page) => $page
            ->has('restorations', 2)
            ->where('restorations.0.holder.plan.code', '40000')
            ->where('conflictIds', [$holderSchool, $holderGrade]));

    // No approval, or a partial one: nothing changes.
    foreach ([[], [$holderSchool]] as $approved) {
        $this->actingAs($this->staff)->post(F::url($this->school, "/{$this->archived}/reactivate"), ['approved_assignment_ids' => $approved])
            ->assertSessionHas('error', 'Hay asignaciones que se reemplazarían y no fueron aprobadas. No se hizo ningún cambio.');

        expect(DB::table('study_plans')->where('id', $this->archived)->value('status'))->toBe('archived')
            ->and(DB::table('study_plan_assignments')->whereNull('replaced_at')->pluck('id')->sort()->values()->all())->toBe([$holderSchool, $holderGrade]);
    }

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$this->archived}/reactivate"), ['approved_assignment_ids' => [$holderSchool, $holderGrade]])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(DB::table('study_plans')->where('id', $this->archived)->value('status'))->toBe('active')
        ->and(DB::table('study_plan_assignments')->whereNull('replaced_at')->pluck('id')->sort()->values()->all())->toBe([$lostSchool, $lostGrade])
        // The replaced holders belonged to an active plan: deleted with their exclusions.
        ->and(DB::table('study_plan_assignments')->whereIn('id', [$holderSchool, $holderGrade])->count())->toBe(0)
        ->and(DB::table('study_plan_subject_exclusions')->count())->toBe(0);
});

test('a holder of another archived plan is kept as history when replaced', function () {
    $lost = F::assignment($this->school, $this->period, $this->archived, 'school', replaced: true);
    $otherArchived = F::plan($this->school, '50000', ['status' => 'archived']);
    $holder = F::assignment($this->school, $this->period, $otherArchived, 'school');

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$this->archived}/reactivate"), ['approved_assignment_ids' => [$holder]])
        ->assertSessionHas('success');

    expect(DB::table('study_plan_assignments')->where('id', $holder)->value('replaced_at'))->not->toBeNull()
        ->and(DB::table('study_plan_assignments')->where('id', $lost)->value('replaced_at'))->toBeNull();
});

test('an active plan has no reactivation: the review redirects and the action is refused', function () {
    $this->actingAs($this->staff)->get(F::url($this->school, "/{$this->other}/reactivation"))
        ->assertRedirect(route('subjects.plans.show', $this->other));

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$this->other}/reactivate"), ['approved_assignment_ids' => []])
        ->assertSessionHas('error');
});

test('the reactivation request needs the approval list', function () {
    $this->actingAs($this->staff)->post(F::url($this->school, "/{$this->archived}/reactivate"), [])
        ->assertSessionHasErrors('approved_assignment_ids');

    expect(DB::table('study_plans')->where('id', $this->archived)->value('status'))->toBe('archived');
});

test('a subject reactivation shows where it becomes active again and needs an active plan', function () {
    $plan = $this->other;
    $subject = F::subject($this->school, $plan, $this->grade1, 'Dibujo', ['status' => 'archived']);
    $covering = F::assignment($this->school, $this->period, $plan, 'school');
    F::assignment($this->school, $this->pastPeriod, $plan, 'school');

    $this->actingAs($this->staff)->get(F::url($this->school, "/{$plan}/subjects/{$subject}/reactivation"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kind', 'subject')
            ->where('subject.gradeLevelName', 'Primer año')
            ->has('coverage', 1)
            ->where('coverage.0.id', $covering)
            ->where('blockedReason', null));

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/subjects/{$subject}/reactivate"))
        ->assertRedirect(route('subjects.plans.show', $plan))
        ->assertSessionHas('success', 'Asignatura reactivada.');
    expect(DB::table('study_plan_subjects')->where('id', $subject)->value('status'))->toBe('active');

    $frozenSubject = F::subject($this->school, $this->archived, $this->grade1, 'Latín', ['status' => 'archived']);
    $this->actingAs($this->staff)->get(F::url($this->school, "/{$this->archived}/subjects/{$frozenSubject}/reactivation"))
        ->assertInertia(fn (Assert $page) => $page->where('blockedReason', 'plan-archived'));
    $this->actingAs($this->staff)->post(F::url($this->school, "/{$this->archived}/subjects/{$frozenSubject}/reactivate"))
        ->assertSessionHas('error', 'El plan está archivado y es de solo lectura.');
    expect(DB::table('study_plan_subjects')->where('id', $frozenSubject)->value('status'))->toBe('archived');
});

test('another school\'s plan cannot be reactivated', function () {
    $foreign = F::plan($this->otherSchool, '1', ['status' => 'archived']);

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$foreign}/reactivate"), ['approved_assignment_ids' => []])->assertNotFound();
    expect(DB::table('study_plans')->where('id', $foreign)->value('status'))->toBe('archived');
});
