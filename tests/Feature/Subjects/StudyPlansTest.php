<?php

use App\ModulePlatform\Services\ModuleRegistry;
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

    $this->staff = F::user($this->school, 'staff/admin');
    $this->teacher = F::user($this->school, 'teacher');
    $this->student = F::user($this->school, 'student');
    $this->grade1 = F::gradeLevel($this->school, 'Primer año', 1);
    $this->grade2 = F::gradeLevel($this->school, 'Segundo año', 2);
});

test('staff lists only the active plans of their school with code, observation and subject count', function () {
    $plan = F::plan($this->school, '31060', ['observation' => 'Versión 2024']);
    F::subject($this->school, $plan, $this->grade1, 'Matemática');
    F::plan($this->school, '99999', ['status' => 'archived']);
    F::plan($this->otherSchool, '31060');

    $this->actingAs($this->staff)->get(F::url($this->school))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subjects::Index')
            ->has('plans.data', 1)
            ->where('plans.data.0.code', '31060')
            ->where('plans.data.0.observation', 'Versión 2024')
            ->where('plans.data.0.subjectCount', 1)
            ->where('counts', ['active' => 1, 'archived' => 1])
            ->where('filters.status', 'active'));
});

test('the archived filter lists archived plans', function () {
    F::plan($this->school, '11111');
    F::plan($this->school, '22222', ['status' => 'archived']);

    $this->actingAs($this->staff)->get(F::url($this->school, '?status=archived'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('plans.data', 1)
            ->where('plans.data.0.code', '22222')
            ->where('filters.status', 'archived'));
});

test('search runs server-side across every page and page links keep the filters', function () {
    foreach (range(1, 20) as $i) {
        F::plan($this->school, sprintf('A%03d', $i));
    }
    F::plan($this->school, 'Z999', ['name' => 'Plan técnico nocturno']);

    $this->actingAs($this->staff)->get(F::url($this->school, '?search=NOCTURNO'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('plans.data', 1)
            ->where('plans.data.0.code', 'Z999')
            ->where('filters.search', 'NOCTURNO'));

    $this->actingAs($this->staff)->get(F::url($this->school, '?search=A0'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('plans.total', 20)
            ->has('plans.data', 15)
            ->where('plans.path', fn (string $path) => str_contains($path, 'search=A0') && ! str_contains($path, 'page=')));
});

test('search ignores accents and case in both directions', function (string $search) {
    F::plan($this->school, '1', ['name' => 'Matemática aplicada']);
    F::plan($this->school, '2', ['name' => 'Matematica basica']);
    F::plan($this->school, '3', ['name' => 'Historia', 'observation' => 'Énfasis en MATEMÁTICA']);
    F::plan($this->school, '4', ['name' => 'Arte']);

    $this->actingAs($this->staff)->get(F::url($this->school, '?search='.urlencode($search)))
        ->assertInertia(fn (Assert $page) => $page
            ->where('plans.data', fn ($plans) => collect($plans)->pluck('code')->all() === ['1', '2', '3']));
})->with(['matematica', 'MATEMÁTICA', 'Matemática', 'mAtEmAtIcA']);

test('a plan saved through the app stays searchable after an edit', function () {
    $this->actingAs($this->staff)->post(F::url($this->school), ['code' => 'Ñ-01', 'name' => 'Educación básica']);
    $plan = DB::table('study_plans')->sole();

    $this->actingAs($this->staff)->get(F::url($this->school, '?search=educacion'))
        ->assertInertia(fn (Assert $page) => $page->has('plans.data', 1));

    $this->actingAs($this->staff)->put(F::url($this->school, "/{$plan->id}"), ['code' => 'Ñ-01', 'name' => 'Ciencias', 'observation' => 'Técnica']);

    $this->actingAs($this->staff)->get(F::url($this->school, '?search=educacion'))
        ->assertInertia(fn (Assert $page) => $page->has('plans.data', 0));
    $this->actingAs($this->staff)->get(F::url($this->school, '?search=tecnica'))
        ->assertInertia(fn (Assert $page) => $page->where('plans.data.0.code', 'Ñ-01'));
    $this->actingAs($this->staff)->get(F::url($this->school, '?search=ñ-01'))
        ->assertInertia(fn (Assert $page) => $page->where('plans.data.0.code', 'Ñ-01'));
});

test('teachers and students get 403 on every route', function (string $who) {
    $user = $this->{$who};
    $plan = F::plan($this->school, '31060');

    $this->actingAs($user)->get(F::url($this->school))->assertForbidden();
    $this->actingAs($user)->post(F::url($this->school), ['code' => '1', 'name' => 'X'])->assertForbidden();
    $this->actingAs($user)->get(F::url($this->school, "/{$plan}"))->assertForbidden();
    $this->actingAs($user)->get(F::url($this->school, '/assignments'))->assertForbidden();
    $this->actingAs($user)->post(F::url($this->school, "/{$plan}/archive"))->assertForbidden();

    expect(DB::table('study_plans')->count())->toBe(1);
})->with(['teacher', 'student']);

test('the sidebar shows the module to staff only', function () {
    $dashboard = 'http://'.$this->school->subdomain.'.'.config('tenancy.base_domain').'/dashboard';

    $this->actingAs($this->staff)->get($dashboard)
        ->assertInertia(fn (Assert $page) => $page->where('moduleNav', fn ($items) => collect($items)->contains('label', 'Planes de estudio')));
    $this->actingAs($this->teacher)->get($dashboard)
        ->assertInertia(fn (Assert $page) => $page->where('moduleNav', fn ($items) => collect($items)->doesntContain('label', 'Planes de estudio')));
});

test('the module returns 404 when the school is not entitled or the module is disabled', function () {
    $plan = F::plan($this->school, '31060');
    app(ModuleRegistry::class)->revoke('subjects', $this->school);

    $this->actingAs($this->staff)->get(F::url($this->school))->assertNotFound();
    $this->actingAs($this->staff)->get(F::url($this->school, "/{$plan}"))->assertNotFound();
    $this->actingAs($this->staff)->post(F::url($this->school), ['code' => '1', 'name' => 'X'])->assertNotFound();
    // 404, not 403, even for a role without access.
    $this->actingAs($this->teacher)->get(F::url($this->school))->assertNotFound();

    app(ModuleRegistry::class)->entitle('subjects', $this->school);
    app(ModuleRegistry::class)->disable('subjects');

    $this->actingAs($this->staff)->get(F::url($this->school))->assertNotFound();
});

test('another school\'s plan and subject are 404', function () {
    $foreignGrade = F::gradeLevel($this->otherSchool, 'Primero', 1);
    $foreign = F::plan($this->otherSchool, '31060');
    $foreignSubject = F::subject($this->otherSchool, $foreign, $foreignGrade, 'Física');

    $this->actingAs($this->staff)->get(F::url($this->school, "/{$foreign}"))->assertNotFound();
    $this->actingAs($this->staff)->put(F::url($this->school, "/{$foreign}"), ['code' => '1', 'name' => 'X'])->assertNotFound();
    $this->actingAs($this->staff)->delete(F::url($this->school, "/{$foreign}"))->assertNotFound();
    $this->actingAs($this->staff)->post(F::url($this->school, "/{$foreign}/archive"))->assertNotFound();
    $this->actingAs($this->staff)->get(F::url($this->school, "/{$foreign}/reactivation"))->assertNotFound();
    $this->actingAs($this->staff)->post(F::url($this->school, "/{$foreign}/subjects"), ['name' => 'X', 'grade_level_id' => $this->grade1])->assertNotFound();
    $this->actingAs($this->staff)->delete(F::url($this->school, "/{$foreign}/subjects/{$foreignSubject}"))->assertNotFound();

    expect(DB::table('study_plans')->where('id', $foreign)->value('status'))->toBe('active')
        ->and(DB::table('study_plan_subjects')->count())->toBe(1);
});

test('staff creates a plan in their own school whatever school_id is posted', function () {
    $response = $this->actingAs($this->staff)->post(F::url($this->school), [
        'code' => ' 31060 ',
        'name' => 'Bachillerato',
        'school_id' => $this->otherSchool->id,
    ]);

    $plan = DB::table('study_plans')->sole();
    $response->assertRedirect(route('subjects.plans.show', $plan->id))->assertSessionHas('success');
    expect($plan->school_id)->toBe($this->school->id)
        ->and($plan->code)->toBe('31060')
        ->and($plan->observation)->toBeNull()
        ->and($plan->status)->toBe('active');
});

test('a repeated code requires an observation, compared case-insensitively and including archived plans', function () {
    F::plan($this->school, 'ab-31060', ['status' => 'archived']);
    F::plan($this->otherSchool, 'XYZ');

    $this->actingAs($this->staff)->from(F::url($this->school))
        ->post(F::url($this->school), ['code' => 'AB-31060', 'name' => 'Otro', 'observation' => '   '])
        ->assertRedirect(F::url($this->school))
        ->assertSessionHasErrors(['observation' => 'Ya existe un plan con este código. Agrega una observación para diferenciarlos.']);
    expect(DB::table('study_plans')->where('school_id', $this->school->id)->count())->toBe(1);

    $this->actingAs($this->staff)
        ->post(F::url($this->school), ['code' => 'AB-31060', 'name' => 'Otro', 'observation' => 'Nocturno'])
        ->assertSessionHasNoErrors();

    // A code used only by another school is not a duplicate.
    $this->actingAs($this->staff)
        ->post(F::url($this->school), ['code' => 'XYZ', 'name' => 'Nuevo'])
        ->assertSessionHasNoErrors();

    expect(DB::table('study_plans')->where('school_id', $this->school->id)->count())->toBe(3);
});

test('editing a plan keeps its own code without requiring an observation', function () {
    $plan = F::plan($this->school, '31060');

    $this->actingAs($this->staff)->put(F::url($this->school, "/{$plan}"), ['code' => '31060', 'name' => 'Renombrado'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Plan de estudio actualizado.');

    expect(DB::table('study_plans')->where('id', $plan)->value('name'))->toBe('Renombrado');
});

test('plan validation boundaries', function (array $input, string $field) {
    $this->actingAs($this->staff)->post(F::url($this->school), $input)->assertSessionHasErrors($field);
    expect(DB::table('study_plans')->count())->toBe(0);
})->with([
    'missing code' => [['name' => 'X'], 'code'],
    'missing name' => [['code' => '1'], 'name'],
    'code too long' => [['code' => str_repeat('1', 51), 'name' => 'X'], 'code'],
    'observation too long' => [['code' => '1', 'name' => 'X', 'observation' => str_repeat('a', 501)], 'observation'],
]);

test('the plan detail groups subjects and flags which can be deleted', function () {
    $plan = F::plan($this->school, '31060');
    $free = F::subject($this->school, $plan, $this->grade2, 'Química');
    $used = F::subject($this->school, $plan, $this->grade1, 'Matemática');
    $period = F::period($this->school, '2026', true);
    F::assignment($this->school, $period, $plan, 'grade_level', $this->grade1);

    $this->actingAs($this->staff)->get(F::url($this->school, "/{$plan}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subjects::Show')
            ->where('canDelete', false)
            ->where('gradeLevels.0.name', 'Primer año')
            ->where('subjects', fn ($subjects) => collect($subjects)->firstWhere('id', $free)['canDelete'] === true
                && collect($subjects)->firstWhere('id', $used)['canDelete'] === false)
            ->has('assignments', 1));
});

test('staff adds and edits subjects; the grade level must belong to the school', function () {
    $plan = F::plan($this->school, '31060');
    $foreignGrade = F::gradeLevel($this->otherSchool, 'Primero', 1);

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/subjects"), [
        'name' => 'Matemática', 'code' => 'MAT1', 'grade_level_id' => $this->grade1, 'weekly_hours' => 5,
    ])->assertSessionHasNoErrors();
    // Duplicates are allowed.
    $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/subjects"), [
        'name' => 'Matemática', 'grade_level_id' => $this->grade1,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/subjects"), [
        'name' => 'Física', 'grade_level_id' => $foreignGrade,
    ])->assertSessionHasErrors(['grade_level_id' => 'Selecciona un año válido.']);

    $subject = DB::table('study_plan_subjects')->where('code', 'MAT1')->sole();
    expect($subject->school_id)->toBe($this->school->id)->and($subject->weekly_hours)->toBe(5);

    $this->actingAs($this->staff)->put(F::url($this->school, "/{$plan}/subjects/{$subject->id}"), [
        'name' => 'Matemática I', 'grade_level_id' => $this->grade2, 'weekly_hours' => '',
    ])->assertSessionHasNoErrors();

    $updated = DB::table('study_plan_subjects')->where('id', $subject->id)->sole();
    expect($updated->name)->toBe('Matemática I')
        ->and($updated->grade_level_id)->toBe($this->grade2)
        ->and($updated->weekly_hours)->toBeNull()
        ->and(DB::table('study_plan_subjects')->count())->toBe(2);
});

test('subject validation boundaries', function (array $input, string $field) {
    $plan = F::plan($this->school, '31060');

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/subjects"), $input + ['grade_level_id' => $this->grade1])
        ->assertSessionHasErrors($field);
    expect(DB::table('study_plan_subjects')->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'zero weekly hours' => [['name' => 'X', 'weekly_hours' => 0], 'weekly_hours'],
    'negative weekly hours' => [['name' => 'X', 'weekly_hours' => -2], 'weekly_hours'],
    'fractional weekly hours' => [['name' => 'X', 'weekly_hours' => 2.5], 'weekly_hours'],
]);

test('a plan without related data is deleted; one with subjects or assignments can only be archived', function () {
    $empty = F::plan($this->school, '1');
    $withSubject = F::plan($this->school, '2');
    F::subject($this->school, $withSubject, $this->grade1, 'Arte');
    $withAssignment = F::plan($this->school, '3');
    F::assignment($this->school, F::period($this->school, '2026', true), $withAssignment, 'school');

    $this->actingAs($this->staff)->delete(F::url($this->school, "/{$empty}"))->assertRedirect(route('subjects.index'));
    expect(DB::table('study_plans')->where('id', $empty)->exists())->toBeFalse();

    foreach ([$withSubject, $withAssignment] as $plan) {
        $this->actingAs($this->staff)->from(F::url($this->school, "/{$plan}"))->delete(F::url($this->school, "/{$plan}"))
            ->assertRedirect(F::url($this->school, "/{$plan}"))
            ->assertSessionHas('error', 'Tiene datos relacionados y no se puede eliminar. Puedes archivarlo.');
        expect(DB::table('study_plans')->where('id', $plan)->exists())->toBeTrue();

        $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/archive"))->assertSessionHas('success', 'Plan de estudio archivado.');
        expect(DB::table('study_plans')->where('id', $plan)->value('status'))->toBe('archived');
    }
});

test('a subject is deleted only when no assignment activates or excludes it in an open period', function () {
    $plan = F::plan($this->school, '31060');
    $period = F::period($this->school, '2026', true);
    $free = F::subject($this->school, $plan, $this->grade2, 'Química');
    $activated = F::subject($this->school, $plan, $this->grade1, 'Matemática');
    $excluded = F::subject($this->school, $plan, $this->grade1, 'Arte');
    $assignment = F::assignment($this->school, $period, $plan, 'grade_level', $this->grade1);
    F::exclude($this->school, $assignment, $excluded);

    $this->actingAs($this->staff)->delete(F::url($this->school, "/{$plan}/subjects/{$free}"))->assertSessionHas('success', 'Asignatura eliminada.');
    expect(DB::table('study_plan_subjects')->where('id', $free)->exists())->toBeFalse();

    foreach ([$activated, $excluded] as $subject) {
        $this->actingAs($this->staff)->delete(F::url($this->school, "/{$plan}/subjects/{$subject}"))
            ->assertSessionHas('error', 'Tiene datos relacionados y no se puede eliminar. Puedes archivarlo.');
        expect(DB::table('study_plan_subjects')->where('id', $subject)->exists())->toBeTrue();
    }

    $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/subjects/{$activated}/archive"))->assertSessionHas('success', 'Asignatura archivada.');
    expect(DB::table('study_plan_subjects')->where('id', $activated)->value('status'))->toBe('archived');
});

test('assignments and exclusions in closed periods never block deleting a subject', function () {
    $this->travelTo('2026-09-28 10:00:00');
    $plan = F::plan($this->school, '31060');
    $closed = F::period($this->school, '2025', false, '2025-01-01', '2025-11-30');
    $open = F::period($this->school, '2026', true, '2026-01-01', '2026-11-30');
    $math = F::subject($this->school, $plan, $this->grade1, 'Matemática');
    $art = F::subject($this->school, $plan, $this->grade1, 'Arte');
    $chem = F::subject($this->school, $plan, $this->grade2, 'Química');
    $history = F::assignment($this->school, $closed, $plan, 'school');
    F::exclude($this->school, $history, $art);
    // A replaced row of an open period is history too: only current assignments activate.
    F::assignment($this->school, $open, $plan, 'grade_level', $this->grade1, replaced: true);
    F::assignment($this->school, $open, $plan, 'grade_level', $this->grade2);

    $this->actingAs($this->staff)->get(F::url($this->school, "/{$plan}"))
        ->assertInertia(fn (Assert $page) => $page->where('subjects', fn ($subjects) => collect($subjects)->pluck('canDelete', 'id')->all() === [
            $art => true, $math => true, $chem => false,
        ]));

    foreach ([$math, $art] as $subject) {
        $this->actingAs($this->staff)->delete(F::url($this->school, "/{$plan}/subjects/{$subject}"))->assertSessionHas('success', 'Asignatura eliminada.');
    }

    expect(DB::table('study_plan_subjects')->pluck('id')->all())->toBe([$chem])
        // The closed period's exclusion went with the subject; its assignment stays.
        ->and(DB::table('study_plan_subject_exclusions')->count())->toBe(0)
        ->and(DB::table('study_plan_assignments')->where('id', $history)->exists())->toBeTrue();

    $this->actingAs($this->staff)->delete(F::url($this->school, "/{$plan}/subjects/{$chem}"))
        ->assertSessionHas('error', 'Tiene datos relacionados y no se puede eliminar. Puedes archivarlo.');
});

test('an exclusion on a replaced assignment of an open period does not block deleting a subject', function () {
    $this->travelTo('2026-09-28 10:00:00');
    $plan = F::plan($this->school, '31060');
    $open = F::period($this->school, '2026', true, '2026-01-01', '2026-11-30');
    $art = F::subject($this->school, $plan, $this->grade1, 'Arte');
    // The archived holder was replaced: its exclusion is history, like the row itself.
    $replaced = F::assignment($this->school, $open, $plan, 'school', replaced: true);
    F::exclude($this->school, $replaced, $art);

    $this->actingAs($this->staff)->delete(F::url($this->school, "/{$plan}/subjects/{$art}"))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('error');

    expect(DB::table('study_plan_subjects')->where('id', $art)->exists())->toBeFalse()
        ->and(DB::table('study_plan_subject_exclusions')->count())->toBe(0)
        ->and(DB::table('study_plan_assignments')->where('id', $replaced)->exists())->toBeTrue();
});

test('an archived plan is read-only: no edits, no new subjects, no subject changes', function () {
    $plan = F::plan($this->school, '31060', ['status' => 'archived']);
    $subject = F::subject($this->school, $plan, $this->grade1, 'Matemática');
    $message = 'El plan está archivado y es de solo lectura.';

    $this->actingAs($this->staff)->put(F::url($this->school, "/{$plan}"), ['code' => '31060', 'name' => 'Nuevo'])->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/subjects"), ['name' => 'X', 'grade_level_id' => $this->grade1])->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->put(F::url($this->school, "/{$plan}/subjects/{$subject}"), ['name' => 'X', 'grade_level_id' => $this->grade1])->assertSessionHas('error', $message);
    $this->actingAs($this->staff)->post(F::url($this->school, "/{$plan}/subjects/{$subject}/archive"))->assertSessionHas('error', $message);

    expect(DB::table('study_plans')->where('id', $plan)->value('name'))->toBe('Plan 31060')
        ->and(DB::table('study_plan_subjects')->count())->toBe(1)
        ->and(DB::table('study_plan_subjects')->where('id', $subject)->value('status'))->toBe('active');
});

test('an archived subject cannot be edited', function () {
    $plan = F::plan($this->school, '31060');
    $subject = F::subject($this->school, $plan, $this->grade1, 'Matemática', ['status' => 'archived']);

    $this->actingAs($this->staff)->put(F::url($this->school, "/{$plan}/subjects/{$subject}"), ['name' => 'X', 'grade_level_id' => $this->grade1])
        ->assertSessionHas('error', 'La asignatura está archivada y es de solo lectura.');
    expect(DB::table('study_plan_subjects')->where('id', $subject)->value('name'))->toBe('Matemática');
});
