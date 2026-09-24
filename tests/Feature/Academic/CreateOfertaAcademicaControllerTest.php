<?php

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(ModulePlatformSeeder::class);
});

function ofertaAcademicUrl(School $school, string $path): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}{$path}";
}

function staffAdminFor(School $school): User
{
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');

    return $staff;
}

test('a valid submission creates the offering and redirects with success', function () {
    $school = School::factory()->create();
    PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);
    $staff = staffAdminFor($school);

    $this->actingAs($staff)
        ->post(ofertaAcademicUrl($school, '/academic/ofertas'), [
            'grado_id' => $grado->id,
            'seccion_id' => $seccion->id,
            'capacity' => 30,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('ofertas_academicas', [
        'school_id' => $school->id,
        'grado_id' => $grado->id,
        'seccion_id' => $seccion->id,
        'capacity' => 30,
    ]);
});

test('a duplicate (periodo, grado, seccion) submission surfaces as a form error', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);
    $staff = staffAdminFor($school);

    OfertaAcademica::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'grado_id' => $grado->id,
        'seccion_id' => $seccion->id,
    ]);

    $this->actingAs($staff)
        ->post(ofertaAcademicUrl($school, '/academic/ofertas'), [
            'grado_id' => $grado->id,
            'seccion_id' => $seccion->id,
            'capacity' => 20,
        ])
        ->assertSessionHasErrors('grado_id');
});

test('a cross-school teacher is rejected on teacher_id without creating a legacy offering', function () {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);
    $teacher = User::factory()->create(['school_id' => $otherSchool->id]);
    $teacher->assignRole('teacher');

    $this->actingAs(staffAdminFor($school))
        ->post(ofertaAcademicUrl($school, '/academic/ofertas'), [
            'grado_id' => $grado->id,
            'seccion_id' => $seccion->id,
            'teacher_id' => $teacher->id,
            'capacity' => 30,
        ])
        ->assertSessionHasErrors('teacher_id');

    $this->assertDatabaseCount('ofertas_academicas', 0);
});

test('a same-school non-teacher is rejected on teacher_id without creating a legacy offering', function () {
    $school = School::factory()->create();
    PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $grado = Grado::factory()->create(['school_id' => $school->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');

    $this->actingAs(staffAdminFor($school))
        ->post(ofertaAcademicUrl($school, '/academic/ofertas'), [
            'grado_id' => $grado->id,
            'seccion_id' => $seccion->id,
            'teacher_id' => $student->id,
            'capacity' => 30,
        ])
        ->assertSessionHasErrors('teacher_id');

    $this->assertDatabaseCount('ofertas_academicas', 0);
});

test('cross-school academic catalogs are rejected on their fields without creating a legacy offering', function (string $field) {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $gradeLevel = Grado::factory()->create([
        'school_id' => $field === 'grado_id' ? $otherSchool->id : $school->id,
    ]);
    $section = Seccion::factory()->create([
        'school_id' => $field === 'seccion_id' ? $otherSchool->id : $school->id,
    ]);

    $this->actingAs(staffAdminFor($school))
        ->post(ofertaAcademicUrl($school, '/academic/ofertas'), [
            'grado_id' => $gradeLevel->id,
            'seccion_id' => $section->id,
            'capacity' => 30,
        ])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('ofertas_academicas', 0);
    $this->assertDatabaseCount('integration_outbox_events', 0);
})->with(['grado_id', 'seccion_id']);
