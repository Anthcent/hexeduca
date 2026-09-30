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

    $this->staff = F::user($this->school, 'staff/admin');
    $this->grade1 = F::gradeLevel($this->school, 'Primer año', 1);
    $this->grade2 = F::gradeLevel($this->school, 'Segundo año', 2);
    $this->plan = F::plan($this->school, '31060');
});

test('staff adds several subjects of one grade level at once, by name', function () {
    $this->actingAs($this->staff)
        ->post(F::url($this->school, "/{$this->plan}/subjects/batch"), [
            'grade_level_id' => $this->grade1,
            'names' => ['Matemática', 'Castellano', 'Inglés'],
            'school_id' => $this->otherSchool->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', '3 asignaturas agregadas.');

    $rows = DB::table('study_plan_subjects')->orderBy('id')->get();
    expect($rows->pluck('name')->all())->toBe(['Matemática', 'Castellano', 'Inglés'])
        ->and($rows->pluck('school_id')->unique()->all())->toBe([$this->school->id])
        ->and($rows->pluck('study_plan_id')->unique()->all())->toBe([$this->plan])
        ->and($rows->pluck('grade_level_id')->unique()->all())->toBe([$this->grade1])
        ->and($rows->pluck('status')->unique()->all())->toBe(['active'])
        ->and($rows->pluck('code')->unique()->all())->toBe([null])
        ->and($rows->pluck('weekly_hours')->unique()->all())->toBe([null]);
});

test('a single name gets the singular message', function () {
    $this->actingAs($this->staff)
        ->post(F::url($this->school, "/{$this->plan}/subjects/batch"), ['grade_level_id' => $this->grade1, 'names' => ['Arte']])
        ->assertSessionHas('success', 'Asignatura agregada.');
});

test('an archived plan takes no new subjects', function () {
    DB::table('study_plans')->where('id', $this->plan)->update(['status' => 'archived']);

    $this->actingAs($this->staff)
        ->post(F::url($this->school, "/{$this->plan}/subjects/batch"), ['grade_level_id' => $this->grade1, 'names' => ['Arte', 'Música']])
        ->assertSessionHas('error', 'El plan está archivado y es de solo lectura.');

    expect(DB::table('study_plan_subjects')->count())->toBe(0);
});

test('the grade level must be the school\'s', function () {
    $foreignGrade = F::gradeLevel($this->otherSchool, 'Primero', 1);

    $this->actingAs($this->staff)
        ->post(F::url($this->school, "/{$this->plan}/subjects/batch"), ['grade_level_id' => $foreignGrade, 'names' => ['Arte']])
        ->assertSessionHasErrors(['grade_level_id' => 'Selecciona un año válido.']);

    expect(DB::table('study_plan_subjects')->count())->toBe(0);
});

test('the batch is limited to 50 non-empty names', function (array $names, string $field, string $message) {
    $this->actingAs($this->staff)
        ->post(F::url($this->school, "/{$this->plan}/subjects/batch"), ['grade_level_id' => $this->grade1, 'names' => $names])
        ->assertSessionHasErrors([$field => $message]);

    expect(DB::table('study_plan_subjects')->count())->toBe(0);
})->with([
    '51 names' => [array_map(fn (int $i) => "Asignatura {$i}", range(1, 51)), 'names', 'Puedes agregar hasta 50 asignaturas a la vez.'],
    'no names' => [[], 'names', 'Escribe al menos una asignatura.'],
    'an empty line' => [['Arte', ''], 'names.1', 'Hay una línea vacía en la lista.'],
]);

test('50 names are accepted', function () {
    $this->actingAs($this->staff)
        ->post(F::url($this->school, "/{$this->plan}/subjects/batch"), ['grade_level_id' => $this->grade1, 'names' => array_map(fn (int $i) => "Asignatura {$i}", range(1, 50))])
        ->assertSessionHasNoErrors();

    expect(DB::table('study_plan_subjects')->count())->toBe(50);
});

test('another school\'s plan is 404', function () {
    $foreign = F::plan($this->otherSchool, '1');

    $this->actingAs($this->staff)
        ->post(F::url($this->school, "/{$foreign}/subjects/batch"), ['grade_level_id' => $this->grade1, 'names' => ['Arte']])
        ->assertNotFound();

    expect(DB::table('study_plan_subjects')->count())->toBe(0);
});

test('the index shows the active period, each plan\'s grade coverage, weekly hours and usage; plans in use come first', function () {
    $period = F::period($this->school, '2026', true);
    $oldPeriod = F::period($this->school, '2025', false, '2025-01-01', '2025-11-30');
    $offer = F::offer($this->school, $period, $this->grade1, F::section($this->school, 'A'));

    F::subject($this->school, $this->plan, $this->grade2, 'Química');
    F::subject($this->school, $this->plan, $this->grade1, 'Matemática', ['weekly_hours' => 4]);
    F::subject($this->school, $this->plan, $this->grade1, 'Arte', ['weekly_hours' => 2]);
    F::subject($this->school, $this->plan, $this->grade1, 'Latín', ['weekly_hours' => 3, 'status' => 'archived']);
    F::assignment($this->school, $period, $this->plan, 'school');
    F::assignment($this->school, $period, $this->plan, 'offer', $this->grade1, $offer);

    // Sorts before 31060 by code, but is not in use in the active period.
    $unused = F::plan($this->school, '10000');
    F::assignment($this->school, $oldPeriod, $unused, 'school');
    F::assignment($this->school, $period, $unused, 'grade_level', $this->grade2, replaced: true);

    $this->actingAs($this->staff)->get(F::url($this->school))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activePeriod', ['id' => $period, 'name' => '2026'])
            ->has('plans.data', 2)
            ->where('plans.data.0.code', '31060')
            ->where('plans.data.0.subjectCount', 3)
            ->where('plans.data.0.weeklyHours', 6)
            ->where('plans.data.0.grades', [
                ['gradeLevelId' => $this->grade1, 'name' => 'Primer año', 'subjectCount' => 2, 'weeklyHours' => 6],
                ['gradeLevelId' => $this->grade2, 'name' => 'Segundo año', 'subjectCount' => 1, 'weeklyHours' => 0],
            ])
            ->where('plans.data.0.usage', fn ($usage) => collect($usage)->sortBy('scope')->values()->all() === [
                ['scope' => 'offer', 'targetLabel' => 'Primer año · Sección A'],
                ['scope' => 'school', 'targetLabel' => 'Todo el colegio'],
            ])
            ->where('plans.data.1.code', '10000')
            ->where('plans.data.1.subjectCount', 0)
            ->where('plans.data.1.grades', [])
            ->where('plans.data.1.usage', []));
});

test('without an active period the index has no usage and keeps the code order', function () {
    F::plan($this->school, '10000');

    $this->actingAs($this->staff)->get(F::url($this->school))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activePeriod', null)
            ->where('plans.data', fn ($plans) => collect($plans)->pluck('code')->all() === ['10000', '31060']
                && collect($plans)->every(fn ($plan) => $plan['usage'] === [])));
});
