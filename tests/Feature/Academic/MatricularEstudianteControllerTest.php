<?php

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(ModulePlatformSeeder::class);
});

function matriculaAcademicUrl(School $school, string $path): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}{$path}";
}

function matriculaStaffAdminFor(School $school): User
{
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');

    return $staff;
}

test('a valid submission enrolls the student and derives school/periodo from the offering', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'capacity' => 10,
    ]);
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');
    $staff = matriculaStaffAdminFor($school);

    $this->actingAs($staff)
        ->post(matriculaAcademicUrl($school, '/academic/matriculas'), [
            'oferta_academica_id' => $oferta->id,
            'student_id' => $student->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('matriculas', [
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'oferta_academica_id' => $oferta->id,
        'student_id' => $student->id,
    ]);
});

test('a submission against a full-capacity offering surfaces as a form error', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'capacity' => 1,
    ]);
    $enrolledStudent = User::factory()->create(['school_id' => $school->id]);
    $enrolledStudent->assignRole('student');
    $newStudent = User::factory()->create(['school_id' => $school->id]);
    $newStudent->assignRole('student');
    $staff = matriculaStaffAdminFor($school);

    $this->actingAs($staff)->post(matriculaAcademicUrl($school, '/academic/matriculas'), [
        'oferta_academica_id' => $oferta->id,
        'student_id' => $enrolledStudent->id,
    ])->assertRedirect();

    $this->actingAs($staff)
        ->post(matriculaAcademicUrl($school, '/academic/matriculas'), [
            'oferta_academica_id' => $oferta->id,
            'student_id' => $newStudent->id,
        ])
        ->assertSessionHasErrors('oferta_academica_id');
});

test('a cross-school student is rejected on student_id without creating a legacy enrollment', function () {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
    ]);
    $student = User::factory()->create(['school_id' => $otherSchool->id]);
    $student->assignRole('student');

    $this->actingAs(matriculaStaffAdminFor($school))
        ->post(matriculaAcademicUrl($school, '/academic/matriculas'), [
            'oferta_academica_id' => $oferta->id,
            'student_id' => $student->id,
        ])
        ->assertSessionHasErrors('student_id');

    $this->assertDatabaseCount('matriculas', 0);
});

test('a same-school non-student is rejected on student_id without creating a legacy enrollment', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $oferta = OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
    ]);
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');

    $this->actingAs(matriculaStaffAdminFor($school))
        ->post(matriculaAcademicUrl($school, '/academic/matriculas'), [
            'oferta_academica_id' => $oferta->id,
            'student_id' => $teacher->id,
        ])
        ->assertSessionHasErrors('student_id');

    $this->assertDatabaseCount('matriculas', 0);
});
