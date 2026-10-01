<?php

use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\TeachingAssignments\Public\Contracts\TeachingAssignmentReader;
use Tests\Feature\Subjects\SubjectsFixtures as S;
use Tests\Feature\TeachingAssignments\TeachingAssignmentsFixtures as T;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-28 10:00:00');
    $this->seed(RoleAndPermissionSeeder::class);

    $this->school = School::factory()->create();
    $this->otherSchool = School::factory()->create();
    $this->seed(ModulePlatformSeeder::class);

    $this->staff = S::user($this->school, 'director');
    $this->ana = S::user($this->school, 'teacher');
    $this->beto = S::user($this->school, 'teacher');
    $this->student = S::user($this->school, 'student');

    $this->grade1 = S::gradeLevel($this->school, 'Primer año', 1);
    $this->grade2 = S::gradeLevel($this->school, 'Segundo año', 2);
    $this->period = S::period($this->school, '2026', true);
    $this->offer1A = S::offer($this->school, $this->period, $this->grade1, S::section($this->school, 'A'));
    $this->offer2A = S::offer($this->school, $this->period, $this->grade2, S::section($this->school, 'A'));

    $this->studyPlan = S::plan($this->school, '31060');
    $this->math = S::subject($this->school, $this->studyPlan, $this->grade1, 'Matemática', ['weekly_hours' => 4]);
    $this->art = S::subject($this->school, $this->studyPlan, $this->grade1, 'Arte');
    $this->chem = S::subject($this->school, $this->studyPlan, $this->grade2, 'Química');
    $this->schoolAssignment = S::assignment($this->school, $this->period, $this->studyPlan, 'school');
});

function assignTeacher(object $test, array $overrides = [])
{
    return $test->actingAs($test->staff)->post(T::url($test->school), $overrides + [
        'period_id' => $test->period,
        'offer_id' => $test->offer1A,
        'subject_id' => $test->math,
        'teacher_id' => $test->ana->id,
        'role' => 'titular',
    ]);
}

test('staff assigns a titular teacher starting today', function () {
    assignTeacher($this, ['school_id' => $this->otherSchool->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Docente asignado.');

    $row = DB::table('teaching_assignments')->sole();
    expect([$row->school_id, $row->academic_period_id, $row->academic_offer_id, $row->study_plan_subject_id, $row->teacher_id, $row->role])
        ->toBe([$this->school->id, $this->period, $this->offer1A, $this->math, $this->ana->id, 'titular'])
        ->and(substr($row->started_on, 0, 10))->toBe('2026-09-28')
        ->and($row->ended_on)->toBeNull();
});

test('reassigning a slot ends the previous holder and keeps it as history', function () {
    assignTeacher($this);
    $this->travelTo('2026-10-05 09:00:00');

    assignTeacher($this, ['teacher_id' => $this->beto->id])->assertSessionHasNoErrors();

    $rows = DB::table('teaching_assignments')->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and([$rows[0]->teacher_id, substr((string) $rows[0]->ended_on, 0, 10)])->toBe([$this->ana->id, '2026-10-05'])
        ->and([$rows[1]->teacher_id, substr($rows[1]->started_on, 0, 10), $rows[1]->ended_on])->toBe([$this->beto->id, '2026-10-05', null]);

    // Re-assigning the current holder changes nothing.
    assignTeacher($this, ['teacher_id' => $this->beto->id])->assertSessionHasNoErrors();
    expect(DB::table('teaching_assignments')->count())->toBe(2);
});

test('the same teacher cannot be titular and substitute of a subject', function () {
    assignTeacher($this);

    assignTeacher($this, ['role' => 'suplente'])
        ->assertSessionHasErrors(['teacher_id' => 'Este docente ya tiene el otro rol en la asignatura.']);

    assignTeacher($this, ['role' => 'suplente', 'teacher_id' => $this->beto->id])->assertSessionHasNoErrors();
    expect(DB::table('teaching_assignments')->pluck('role', 'teacher_id')->all())
        ->toBe([$this->ana->id => 'titular', $this->beto->id => 'suplente']);
});

test('only subjects in force for the offer can be assigned', function (string $case) {
    $subject = match ($case) {
        'another grade level' => $this->chem,
        'archived' => S::subject($this->school, $this->studyPlan, $this->grade1, 'Latín', ['status' => 'archived']),
        'excluded' => tap($this->art, fn ($art) => S::exclude($this->school, $this->schoolAssignment, $art)),
        'another plan' => S::subject($this->school, S::plan($this->school, '40000'), $this->grade1, 'Taller'),
    };

    assignTeacher($this, ['subject_id' => $subject])
        ->assertSessionHasErrors(['subject_id' => 'La asignatura no está vigente para esta sección.']);

    expect(DB::table('teaching_assignments')->count())->toBe(0);
})->with(['another grade level', 'archived', 'excluded', 'another plan']);

test('the teacher must be a teacher of the school', function () {
    $foreign = S::user($this->otherSchool, 'teacher');

    foreach ([$foreign->id, $this->student->id, $this->staff->id, 999999] as $teacherId) {
        assignTeacher($this, ['teacher_id' => $teacherId])
            ->assertSessionHasErrors(['teacher_id' => 'Selecciona un docente de la institución.']);
    }

    expect(DB::table('teaching_assignments')->count())->toBe(0);
});

test('the period and the offer must be the school\'s', function () {
    $foreignPeriod = S::period($this->otherSchool, '2026', true);
    $otherPeriod = S::period($this->school, '2027', false, '2027-01-01', '2027-12-31');

    assignTeacher($this, ['period_id' => $foreignPeriod])->assertSessionHasErrors(['period_id' => 'Selecciona un periodo válido.']);
    assignTeacher($this, ['period_id' => $otherPeriod])->assertSessionHasErrors(['offer_id' => 'La sección no pertenece al periodo.']);

    expect(DB::table('teaching_assignments')->count())->toBe(0);
});

test('a closed period is read-only', function () {
    $closed = S::period($this->school, '2025', false, '2025-01-01', '2025-11-30');
    $closedOffer = S::offer($this->school, $closed, $this->grade1, S::section($this->school, 'B'));
    $held = T::assignment($this->school, $closed, $closedOffer, $this->math, $this->ana->id);
    $before = [DB::table('teaching_assignments')->get(), DB::table('teaching_offer_coordinators')->get()];
    $message = 'El periodo está cerrado; sus asignaciones son de solo lectura.';

    assignTeacher($this, ['period_id' => $closed, 'offer_id' => $closedOffer, 'teacher_id' => $this->beto->id])->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->delete(T::url($this->school, "/{$held}"))->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->put(T::url($this->school, "/offers/{$closedOffer}/coordinator"), ['period_id' => $closed, 'teacher_id' => $this->beto->id])
        ->assertSessionHas('error', $message);

    expect([DB::table('teaching_assignments')->get(), DB::table('teaching_offer_coordinators')->get()])->toEqual($before);
});

test('ending an assignment keeps the row as history', function () {
    $this->travelTo('2026-10-01 09:00:00');
    $id = T::assignment($this->school, $this->period, $this->offer1A, $this->math, $this->ana->id);

    $this->actingAs($this->staff)->delete(T::url($this->school, "/{$id}"))->assertSessionHas('success', 'Asignación finalizada.');

    expect(substr((string) DB::table('teaching_assignments')->where('id', $id)->value('ended_on'), 0, 10))->toBe('2026-10-01');

    // Already ended, or another school's: 404.
    $this->actingAs($this->staff)->delete(T::url($this->school, "/{$id}"))->assertNotFound();
    $foreignPeriod = S::period($this->otherSchool, '2026', true);
    $foreignGrade = S::gradeLevel($this->otherSchool, 'Primero', 1);
    $foreignOffer = S::offer($this->otherSchool, $foreignPeriod, $foreignGrade, S::section($this->otherSchool, 'A'));
    $foreign = T::assignment($this->otherSchool, $foreignPeriod, $foreignOffer, 1, S::user($this->otherSchool, 'teacher')->id);
    $this->actingAs($this->staff)->delete(T::url($this->school, "/{$foreign}"))->assertNotFound();

    expect(DB::table('teaching_assignments')->where('id', $foreign)->value('ended_on'))->toBeNull();
});

test('staff sets and clears the coordinator of an offer', function () {
    $url = T::url($this->school, "/offers/{$this->offer1A}/coordinator");

    $this->actingAs($this->staff)->put($url, ['period_id' => $this->period, 'teacher_id' => $this->ana->id])
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Coordinador asignado.');
    $this->actingAs($this->staff)->put($url, ['period_id' => $this->period, 'teacher_id' => $this->beto->id])->assertSessionHasNoErrors();

    $row = DB::table('teaching_offer_coordinators')->sole();
    expect([$row->school_id, $row->academic_period_id, $row->academic_offer_id, $row->teacher_id])
        ->toBe([$this->school->id, $this->period, $this->offer1A, $this->beto->id]);

    $this->actingAs($this->staff)->put($url, ['period_id' => $this->period, 'teacher_id' => $this->student->id])
        ->assertSessionHasErrors(['teacher_id' => 'Selecciona un docente de la institución.']);

    $this->actingAs($this->staff)->put($url, ['period_id' => $this->period, 'teacher_id' => null])
        ->assertSessionHas('success', 'Coordinador quitado.');
    expect(DB::table('teaching_offer_coordinators')->count())->toBe(0);
});

test('teachers and students get 403', function () {
    foreach ([$this->ana, $this->student] as $user) {
        $this->actingAs($user)->get(T::url($this->school))->assertForbidden();
        $this->actingAs($user)->post(T::url($this->school), [
            'period_id' => $this->period, 'offer_id' => $this->offer1A, 'subject_id' => $this->math, 'teacher_id' => $this->ana->id, 'role' => 'titular',
        ])->assertForbidden();
    }

    expect(DB::table('teaching_assignments')->count())->toBe(0);
});

test('a school not entitled to the module gets 404, whatever the role', function () {
    app(ModuleRegistry::class)->revoke('teachingassignments', $this->school);

    $this->actingAs($this->staff)->get(T::url($this->school))->assertNotFound();
    assignTeacher($this)->assertNotFound();
    $this->actingAs($this->ana)->get(T::url($this->school))->assertNotFound();

    expect(DB::table('teaching_assignments')->count())->toBe(0);
});

test('the board shows each offer\'s subjects in force, who teaches them, the orientador and the coordinator', function () {
    DB::table('ofertas_academicas')->where('id', $this->offer1A)->update(['teacher_id' => $this->beto->id]);
    $titular = T::assignment($this->school, $this->period, $this->offer1A, $this->math, $this->ana->id);
    T::assignment($this->school, $this->period, $this->offer1A, $this->art, $this->beto->id, 'suplente');
    T::assignment($this->school, $this->period, $this->offer1A, $this->art, $this->ana->id, 'titular', '2026-09-01');
    DB::table('teaching_offer_coordinators')->insert([
        'school_id' => $this->school->id, 'academic_period_id' => $this->period, 'academic_offer_id' => $this->offer1A,
        'teacher_id' => $this->ana->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    S::user($this->otherSchool, 'teacher');

    $this->actingAs($this->staff)->get(T::url($this->school))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TeachingAssignments::Index')
            ->where('period.id', $this->period)
            ->where('period.isOpen', true)
            ->has('board.teachers', 2)
            ->has('board.offers', 2)
            ->where('board.offers.0.id', $this->offer1A)
            ->where('board.offers.0.orientador.id', $this->beto->id)
            ->where('board.offers.0.coordinatorId', $this->ana->id)
            ->where('board.offers.0.assignedCount', 1)
            ->has('board.offers.0.subjects', 2)
            ->where('board.offers.0.subjects', function ($subjects) use ($titular) {
                $subjects = collect($subjects)->keyBy('name');

                return $subjects['Matemática']['titular']['assignmentId'] === $titular
                    && $subjects['Matemática']['titular']['teacherId'] === $this->ana->id
                    && $subjects['Matemática']['weeklyHours'] === 4
                    && $subjects['Matemática']['substitute'] === null
                    // The ended titular is history, not shown.
                    && $subjects['Arte']['titular'] === null
                    && $subjects['Arte']['substitute']['teacherId'] === $this->beto->id;
            })
            ->where('board.offers.1.id', $this->offer2A)
            ->where('board.offers.1.orientador', null)
            ->where('board.offers.1.assignedCount', 0)
            ->where('board.offers.1.subjects.0.name', 'Química'));
});

test('the public reader answers who teaches what, active assignments only', function () {
    T::assignment($this->school, $this->period, $this->offer1A, $this->math, $this->ana->id);
    T::assignment($this->school, $this->period, $this->offer2A, $this->chem, $this->ana->id, 'suplente');
    T::assignment($this->school, $this->period, $this->offer1A, $this->art, $this->ana->id, 'titular', '2026-09-01');
    $next = S::period($this->school, '2027', false, '2027-01-01', '2027-12-31');
    $nextOffer = S::offer($this->school, $next, $this->grade1, S::section($this->school, 'B'));
    T::assignment($this->school, $next, $nextOffer, $this->math, $this->ana->id);

    $reader = app(TeachingAssignmentReader::class);
    $mine = $reader->forTeacher($this->school->id, $this->period, $this->ana->id);

    expect(array_map(fn ($a) => [$a->offerId, $a->subjectId, $a->role, $a->endedOn], $mine))->toBe([
        [$this->offer1A, $this->math, 'titular', null],
        [$this->offer2A, $this->chem, 'suplente', null],
    ])
        ->and($reader->forTeacher($this->school->id, $this->period, $this->beto->id))->toBe([])
        ->and($reader->forTeacher($this->otherSchool->id, $this->period, $this->ana->id))->toBe([])
        ->and($reader->teaches($this->school->id, $this->ana->id, $this->offer1A, $this->math))->toBeTrue()
        ->and($reader->teaches($this->school->id, $this->ana->id, $this->offer2A, $this->chem))->toBeTrue()
        ->and($reader->teaches($this->school->id, $this->ana->id, $this->offer1A, $this->art))->toBeFalse()
        ->and($reader->teaches($this->school->id, $this->beto->id, $this->offer1A, $this->math))->toBeFalse()
        ->and($reader->teaches($this->otherSchool->id, $this->ana->id, $this->offer1A, $this->math))->toBeFalse();
});
