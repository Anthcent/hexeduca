<?php

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
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
    $sectionA = F::section($this->school, 'A');
    $sectionB = F::section($this->school, 'B');

    $this->period = F::period($this->school, '2026', true);
    $this->offer1A = F::offer($this->school, $this->period, $this->grade1, $sectionA);
    $this->offer1B = F::offer($this->school, $this->period, $this->grade1, $sectionB);
    $this->offer2A = F::offer($this->school, $this->period, $this->grade2, $sectionA);

    $this->general = F::plan($this->school, '31060');
    $this->math1 = F::subject($this->school, $this->general, $this->grade1, 'Matemática');
    $this->art1 = F::subject($this->school, $this->general, $this->grade1, 'Arte');
    $this->chem2 = F::subject($this->school, $this->general, $this->grade2, 'Química');

    $this->technical = F::plan($this->school, '40000', ['observation' => 'Técnico']);
    $this->tech1 = F::subject($this->school, $this->technical, $this->grade1, 'Taller');
});

function boardOffer(array $offers, int $id): array
{
    return collect($offers)->firstWhere('id', $id);
}

test('the board defaults to the active period and resolves each offer by the most specific scope', function () {
    F::period($this->school, '2025', false, '2025-01-01', '2025-11-30');
    F::assignment($this->school, $this->period, $this->general, 'school');
    F::assignment($this->school, $this->period, $this->technical, 'grade_level', $this->grade2);
    F::assignment($this->school, $this->period, $this->technical, 'offer', $this->grade1, $this->offer1B);

    $this->actingAs($this->staff)->get(F::url($this->school, '/assignments'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subjects::Assignments')
            ->where('period.id', $this->period)
            ->has('periods', 2)
            ->where('board.assignments.school.plan.code', '31060')
            ->has('board.assignments.gradeLevels', 1)
            ->has('board.assignments.offers', 1)
            ->where('board.offers', function ($offers) {
                $offers = collect($offers)->all();
                $a = boardOffer($offers, $this->offer1A);
                $b = boardOffer($offers, $this->offer1B);
                $c = boardOffer($offers, $this->offer2A);

                return $a['effective']['scope'] === 'school' && $a['effective']['plan']['code'] === '31060'
                    && collect($a['subjects'])->pluck('name')->sort()->values()->all() === ['Arte', 'Matemática']
                    && $b['effective']['scope'] === 'offer' && $b['effective']['plan']['code'] === '40000'
                    && collect($b['subjects'])->pluck('name')->all() === ['Taller']
                    && $c['effective']['scope'] === 'grade_level'
                    // The technical plan has no second-year subjects.
                    && $c['subjects'] === [];
            }));
});

test('a period without assignments shows offers without a plan, and ?period selects another period', function () {
    $next = F::period($this->school, '2027', false, now()->addYear()->toDateString(), now()->addYears(2)->toDateString());

    $this->actingAs($this->staff)->get(F::url($this->school, "/assignments?period={$next}"))
        ->assertInertia(fn (Assert $page) => $page->where('period.id', $next)->where('board.offers', []));

    $this->actingAs($this->staff)->get(F::url($this->school, '/assignments'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.offers', fn ($offers) => collect($offers)->every(fn ($offer) => $offer['effective'] === null)));
});

test('staff assigns a plan per scope; the school always comes from the tenant', function () {
    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $this->period, 'plan_id' => $this->general, 'scope' => 'school', 'school_id' => $this->otherSchool->id,
    ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Plan asignado.');

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $this->period, 'plan_id' => $this->technical, 'scope' => 'grade_level', 'grade_level_id' => $this->grade2,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $this->period, 'plan_id' => $this->technical, 'scope' => 'offer', 'offer_id' => $this->offer1A,
    ])->assertSessionHasNoErrors();

    $rows = DB::table('study_plan_assignments')->orderBy('id')->get();
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('school_id')->unique()->all())->toBe([$this->school->id])
        ->and([$rows[0]->scope, $rows[0]->target_id, $rows[0]->grade_level_id])->toBe(['school', 0, null])
        ->and([$rows[1]->scope, $rows[1]->target_id])->toBe(['grade_level', $this->grade2])
        // The offer scope also stores the offer's grade level.
        ->and([$rows[2]->scope, $rows[2]->target_id, $rows[2]->grade_level_id, $rows[2]->academic_offer_id])
        ->toBe(['offer', $this->offer1A, $this->grade1, $this->offer1A]);
});

test('changing the plan of a slot replaces the holder and drops its exclusions', function () {
    $old = F::assignment($this->school, $this->period, $this->general, 'school');
    F::exclude($this->school, $old, $this->art1);

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $this->period, 'plan_id' => $this->technical, 'scope' => 'school',
    ])->assertSessionHasNoErrors();

    expect(DB::table('study_plan_assignments')->where('id', $old)->exists())->toBeFalse()
        ->and(DB::table('study_plan_subject_exclusions')->count())->toBe(0)
        ->and(DB::table('study_plan_assignments')->sole()->study_plan_id)->toBe($this->technical);
});

test('re-assigning the plan that already holds the slot keeps it and its exclusions', function () {
    $current = F::assignment($this->school, $this->period, $this->general, 'school');
    F::exclude($this->school, $current, $this->art1);

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $this->period, 'plan_id' => $this->general, 'scope' => 'school',
    ])->assertSessionHasNoErrors();

    expect(DB::table('study_plan_assignments')->sole()->id)->toBe($current)
        ->and(DB::table('study_plan_subject_exclusions')->count())->toBe(1);
});

test('the database allows only one current assignment per slot', function () {
    F::assignment($this->school, $this->period, $this->general, 'school');
    // A replaced (history) row may share the slot.
    F::assignment($this->school, $this->period, $this->technical, 'school', replaced: true);

    // The savepoint keeps the outer test transaction usable on PostgreSQL,
    // where a failed statement aborts the whole transaction.
    expect(fn () => DB::transaction(fn () => F::assignment($this->school, $this->period, $this->technical, 'school')))->toThrow(QueryException::class);
    expect(fn () => F::assignment($this->school, $this->period, $this->technical, 'grade_level', $this->grade1))->not->toThrow(QueryException::class);
});

test('an archived plan cannot be newly assigned', function () {
    DB::table('study_plans')->where('id', $this->technical)->update(['status' => 'archived']);

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $this->period, 'plan_id' => $this->technical, 'scope' => 'school',
    ])->assertSessionHas('error', 'El plan está archivado y es de solo lectura.');

    expect(DB::table('study_plan_assignments')->count())->toBe(0);
});

test('assignment targets must belong to the school and the period', function (string $case, string $scope, string $field) {
    $otherPeriod = F::period($this->school, '2027');

    $target = match ($case) {
        'foreign period' => ['period_id' => F::period($this->otherSchool, '2026')],
        'foreign grade level' => ['grade_level_id' => F::gradeLevel($this->otherSchool, 'X', 1)],
        'offer of another period' => ['offer_id' => F::offer($this->school, $otherPeriod, $this->grade1, F::section($this->school, 'Z'))],
        default => [],
    };

    $this->actingAs($this->staff)
        ->post(F::url($this->school, '/assignments'), $target + ['period_id' => $this->period, 'plan_id' => $this->general, 'scope' => $scope])
        ->assertSessionHasErrors($field);

    expect(DB::table('study_plan_assignments')->count())->toBe(0);
})->with([
    ['foreign period', 'school', 'period_id'],
    ['foreign grade level', 'grade_level', 'grade_level_id'],
    ['offer of another period', 'offer', 'offer_id'],
    ['missing grade level', 'grade_level', 'grade_level_id'],
    ['missing offer', 'offer', 'offer_id'],
    ['unknown scope', 'section', 'scope'],
]);

test('another school\'s plan and assignment are 404', function () {
    $foreignPlan = F::plan($this->otherSchool, '1');
    $foreignPeriod = F::period($this->otherSchool, '2026', true);
    $foreignAssignment = F::assignment($this->otherSchool, $foreignPeriod, $foreignPlan, 'school');

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $this->period, 'plan_id' => $foreignPlan, 'scope' => 'school',
    ])->assertNotFound();
    $this->actingAs($this->staff)->delete(F::url($this->school, "/assignments/{$foreignAssignment}"))->assertNotFound();
    $this->actingAs($this->staff)->put(F::url($this->school, "/assignments/{$foreignAssignment}/exclusions"), ['subject_id' => $this->math1, 'excluded' => true])->assertNotFound();

    expect(DB::table('study_plan_assignments')->where('id', $foreignAssignment)->exists())->toBeTrue();
});

test('"only this section" with another school\'s period and offer is refused and changes nothing', function () {
    $foreignPlan = F::plan($this->otherSchool, '1');
    $foreignGrade = F::gradeLevel($this->otherSchool, 'Primero', 1);
    $foreignSubject = F::subject($this->otherSchool, $foreignPlan, $foreignGrade, 'Física');
    $foreignPeriod = F::period($this->otherSchool, '2026', true);
    $foreignOffer = F::offer($this->otherSchool, $foreignPeriod, $foreignGrade, F::section($this->otherSchool, 'A'));
    F::assignment($this->otherSchool, $foreignPeriod, $foreignPlan, 'school');
    $before = [DB::table('study_plan_assignments')->get(), DB::table('study_plan_subject_exclusions')->get()];

    // Like any other period that is not the school's: a period_id field error.
    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments/offer-override'), [
        'period_id' => $foreignPeriod, 'offer_id' => $foreignOffer, 'subject_id' => $foreignSubject, 'excluded' => true,
    ])->assertSessionHasErrors(['period_id' => 'Selecciona un periodo válido.']);

    expect([DB::table('study_plan_assignments')->get(), DB::table('study_plan_subject_exclusions')->get()])->toEqual($before);
});

test('excluding a subject on a broad assignment affects every offer inheriting it, and it can be included again', function () {
    $school = F::assignment($this->school, $this->period, $this->general, 'school');

    $this->actingAs($this->staff)->put(F::url($this->school, "/assignments/{$school}/exclusions"), ['subject_id' => $this->art1, 'excluded' => true])
        ->assertSessionHas('success', 'Asignatura excluida.');

    $this->actingAs($this->staff)->get(F::url($this->school, '/assignments'))
        ->assertInertia(fn (Assert $page) => $page->where('board.offers', function ($offers) {
            $offers = collect($offers)->all();

            return collect([$this->offer1A, $this->offer1B])->every(fn ($id) => collect(boardOffer($offers, $id)['subjects'])->firstWhere('name', 'Arte')['active'] === false
                && collect(boardOffer($offers, $id)['subjects'])->firstWhere('name', 'Matemática')['active'] === true);
        }));

    $this->actingAs($this->staff)->put(F::url($this->school, "/assignments/{$school}/exclusions"), ['subject_id' => $this->art1, 'excluded' => false])
        ->assertSessionHas('success', 'Asignatura incluida.');
    expect(DB::table('study_plan_subject_exclusions')->count())->toBe(0);
});

test('an exclusion must name an active subject of the assigned plan for a covered grade level', function () {
    $grade2Assignment = F::assignment($this->school, $this->period, $this->general, 'grade_level', $this->grade2);
    $archived = F::subject($this->school, $this->general, $this->grade2, 'Latín', ['status' => 'archived']);

    foreach ([$this->math1, $this->tech1, $archived] as $subject) {
        $this->actingAs($this->staff)->put(F::url($this->school, "/assignments/{$grade2Assignment}/exclusions"), ['subject_id' => $subject, 'excluded' => true])
            ->assertSessionHasErrors(['subject_id' => 'La asignatura no pertenece al plan asignado.']);
    }

    expect(DB::table('study_plan_subject_exclusions')->count())->toBe(0);
});

test('"only this section" creates an offer assignment of the same plan with the inherited exclusions', function () {
    $school = F::assignment($this->school, $this->period, $this->general, 'school');
    F::exclude($this->school, $school, $this->art1);

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments/offer-override'), [
        'period_id' => $this->period, 'offer_id' => $this->offer1A, 'subject_id' => $this->math1, 'excluded' => true,
    ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Se aplicó el cambio solo a esta sección.');

    $override = DB::table('study_plan_assignments')->where('scope', 'offer')->sole();
    expect($override->study_plan_id)->toBe($this->general)
        ->and($override->academic_offer_id)->toBe($this->offer1A)
        ->and(DB::table('study_plan_subject_exclusions')->where('study_plan_assignment_id', $override->id)->pluck('study_plan_subject_id')->sort()->values()->all())
        ->toBe(collect([$this->math1, $this->art1])->sort()->values()->all())
        // The school assignment keeps only its own exclusion.
        ->and(DB::table('study_plan_subject_exclusions')->where('study_plan_assignment_id', $school)->pluck('study_plan_subject_id')->all())->toBe([$this->art1]);

    $this->actingAs($this->staff)->get(F::url($this->school, '/assignments'))
        ->assertInertia(fn (Assert $page) => $page->where('board.offers', function ($offers) {
            $offers = collect($offers)->all();

            return boardOffer($offers, $this->offer1A)['effective']['scope'] === 'offer'
                && collect(boardOffer($offers, $this->offer1A)['subjects'])->firstWhere('name', 'Matemática')['active'] === false
                && collect(boardOffer($offers, $this->offer1B)['subjects'])->firstWhere('name', 'Matemática')['active'] === true;
        }));
});

test('"only this section" on an offer that already has its own assignment edits that assignment', function () {
    $own = F::assignment($this->school, $this->period, $this->general, 'offer', $this->grade1, $this->offer1A);

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments/offer-override'), [
        'period_id' => $this->period, 'offer_id' => $this->offer1A, 'subject_id' => $this->math1, 'excluded' => true,
    ])->assertSessionHasNoErrors();

    expect(DB::table('study_plan_assignments')->count())->toBe(1)
        ->and(DB::table('study_plan_subject_exclusions')->sole()->study_plan_assignment_id)->toBe($own);
});

test('"only this section" needs an effective plan', function () {
    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments/offer-override'), [
        'period_id' => $this->period, 'offer_id' => $this->offer1A, 'subject_id' => $this->math1, 'excluded' => true,
    ])->assertSessionHasErrors('offer_id');

    expect(DB::table('study_plan_assignments')->count())->toBe(0);
});

test('removing an assignment of an active plan deletes it with its exclusions', function () {
    $assignment = F::assignment($this->school, $this->period, $this->general, 'grade_level', $this->grade1);
    F::exclude($this->school, $assignment, $this->art1);

    $this->actingAs($this->staff)->delete(F::url($this->school, "/assignments/{$assignment}"))->assertSessionHas('success', 'Asignación quitada.');

    expect(DB::table('study_plan_assignments')->count())->toBe(0)
        ->and(DB::table('study_plan_subject_exclusions')->count())->toBe(0);
});

test('an archived plan keeps its assignments frozen until another plan takes the slot', function () {
    $frozen = F::assignment($this->school, $this->period, $this->general, 'school');
    F::exclude($this->school, $frozen, $this->art1);
    DB::table('study_plans')->where('id', $this->general)->update(['status' => 'archived']);

    // Still effective, but read-only.
    $this->actingAs($this->staff)->get(F::url($this->school, '/assignments'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.assignments.school.plan.archived', true)
            ->where('plans', fn ($plans) => collect($plans)->pluck('code')->all() === ['40000']));
    $this->actingAs($this->staff)->put(F::url($this->school, "/assignments/{$frozen}/exclusions"), ['subject_id' => $this->math1, 'excluded' => true])
        ->assertSessionHas('error', 'El plan está archivado y es de solo lectura.');

    // Another plan takes the slot: the archived one's assignment becomes history.
    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $this->period, 'plan_id' => $this->technical, 'scope' => 'school',
    ])->assertSessionHasNoErrors();

    $history = DB::table('study_plan_assignments')->where('id', $frozen)->sole();
    expect($history->replaced_at)->not->toBeNull()
        ->and(DB::table('study_plan_subject_exclusions')->where('study_plan_assignment_id', $frozen)->count())->toBe(1)
        ->and(DB::table('study_plan_assignments')->whereNull('replaced_at')->sole()->study_plan_id)->toBe($this->technical);
});

test('removing an archived plan\'s assignment keeps it as history', function () {
    $frozen = F::assignment($this->school, $this->period, $this->general, 'school');
    DB::table('study_plans')->where('id', $this->general)->update(['status' => 'archived']);

    $this->actingAs($this->staff)->delete(F::url($this->school, "/assignments/{$frozen}"))->assertSessionHas('success');

    expect(DB::table('study_plan_assignments')->where('id', $frozen)->value('replaced_at'))->not->toBeNull();
    // A replaced assignment is no longer current: removing it again is 404.
    $this->actingAs($this->staff)->delete(F::url($this->school, "/assignments/{$frozen}"))->assertNotFound();
});

test('a closed period is read-only: every assignment and exclusion change is refused', function () {
    $this->travelTo('2026-09-28 10:00:00');
    $closed = F::period($this->school, '2025', false, '2025-01-01', '2025-11-30');
    $closedOffer = F::offer($this->school, $closed, $this->grade1, F::section($this->school, 'C'));
    $holder = F::assignment($this->school, $closed, $this->general, 'school');
    F::exclude($this->school, $holder, $this->art1);
    $before = [DB::table('study_plan_assignments')->get(), DB::table('study_plan_subject_exclusions')->get()];
    $message = 'El periodo está cerrado; sus asignaciones son de solo lectura.';

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $closed, 'plan_id' => $this->technical, 'scope' => 'school',
    ])->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $closed, 'plan_id' => $this->technical, 'scope' => 'offer', 'offer_id' => $closedOffer,
    ])->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->delete(F::url($this->school, "/assignments/{$holder}"))->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->put(F::url($this->school, "/assignments/{$holder}/exclusions"), ['subject_id' => $this->math1, 'excluded' => true])
        ->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments/offer-override'), [
        'period_id' => $closed, 'offer_id' => $closedOffer, 'subject_id' => $this->math1, 'excluded' => true,
    ])->assertSessionHas('error', $message);

    expect([DB::table('study_plan_assignments')->get(), DB::table('study_plan_subject_exclusions')->get()])->toEqual($before);

    // Viewing it stays allowed.
    $this->actingAs($this->staff)->get(F::url($this->school, "/assignments?period={$closed}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.isOpen', false)
            ->where('board.assignments.school.excludedCount', 1));
});

test('an open period still accepts every assignment change', function () {
    $this->travelTo('2026-09-28 10:00:00');
    // Not the active one, but it has not ended yet.
    $open = F::period($this->school, '2026-B', false, '2026-06-01', '2026-09-28');
    $offer = F::offer($this->school, $open, $this->grade1, F::section($this->school, 'C'));

    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments'), [
        'period_id' => $open, 'plan_id' => $this->general, 'scope' => 'school',
    ])->assertSessionHas('success', 'Plan asignado.');
    $school = DB::table('study_plan_assignments')->where('academic_period_id', $open)->sole()->id;

    $this->actingAs($this->staff)->put(F::url($this->school, "/assignments/{$school}/exclusions"), ['subject_id' => $this->art1, 'excluded' => true])
        ->assertSessionHas('success', 'Asignatura excluida.');
    $this->actingAs($this->staff)->post(F::url($this->school, '/assignments/offer-override'), [
        'period_id' => $open, 'offer_id' => $offer, 'subject_id' => $this->math1, 'excluded' => true,
    ])->assertSessionHas('success', 'Se aplicó el cambio solo a esta sección.');
    $this->actingAs($this->staff)->delete(F::url($this->school, "/assignments/{$school}"))->assertSessionHas('success', 'Asignación quitada.');

    expect(DB::table('study_plan_assignments')->where('academic_period_id', $open)->pluck('scope')->all())->toBe(['offer']);
});

test('the board flags an open period and counts the exclusions of each slot', function () {
    $this->travelTo('2026-09-28 10:00:00');
    $school = F::assignment($this->school, $this->period, $this->general, 'school');
    F::exclude($this->school, $school, $this->art1);
    F::exclude($this->school, $school, $this->math1);
    $grade = F::assignment($this->school, $this->period, $this->technical, 'grade_level', $this->grade1);
    $offer = F::assignment($this->school, $this->period, $this->general, 'offer', $this->grade1, $this->offer1A);
    F::exclude($this->school, $offer, $this->math1);

    $this->actingAs($this->staff)->get(F::url($this->school, '/assignments'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.isOpen', true)
            ->where('board.assignments.school.excludedCount', 2)
            ->where('board.assignments.gradeLevels.0.id', $grade)
            ->where('board.assignments.gradeLevels.0.excludedCount', 0)
            ->where('board.assignments.offers.0.excludedCount', 1));
});
