<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Infrastructure\Database\Seeders\AcademicDatabaseSeeder;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Academic\Infrastructure\Models\Seccion;
use Modules\Users\Infrastructure\Models\User;

/**
 * End-to-end smoke test for the real `AcademicDatabaseSeeder`, not a
 * hand-rolled factory setup: it proves the demo seeder's own output is
 * genuinely usable through both create-offering and enroll-student flows,
 * exactly as a maintainer would exercise it after `migrate:fresh --seed`.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(AcademicDatabaseSeeder::class);
});

function demoUrl(string $path): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://demo.{$baseDomain}{$path}";
}

test('the demo staff/admin can create an offering and enroll a student using only seeded data', function () {
    $school = School::where('subdomain', 'demo')->firstOrFail();
    $staff = User::where('email', 'staff@demo.test')->firstOrFail();

    expect($staff->hasRole('staff/admin'))->toBeTrue();

    $createResponse = $this->actingAs($staff)->get(demoUrl('/academic/ofertas/create'));

    $createResponse->assertInertia(fn ($page) => $page
        ->component('Academic::OfertaCreate')
        ->where('hasActivePeriodo', true)
        ->has('grados', 2)
        ->has('secciones', 2)
        ->has('teachers', 1)
    );

    $grado = Grado::where('school_id', $school->id)->firstOrFail();
    $seccion = Seccion::where('school_id', $school->id)->firstOrFail();
    $teacher = User::where('email', 'teacher@demo.test')->firstOrFail();

    $this->actingAs($staff)
        ->post(demoUrl('/academic/ofertas'), [
            'grado_id' => $grado->id,
            'seccion_id' => $seccion->id,
            'teacher_id' => $teacher->id,
            'capacity' => 25,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $oferta = OfertaAcademica::where('school_id', $school->id)
        ->where('grado_id', $grado->id)
        ->where('seccion_id', $seccion->id)
        ->firstOrFail();

    $matriculaCreateResponse = $this->actingAs($staff)->get(demoUrl('/academic/matriculas/create'));

    $matriculaCreateResponse->assertInertia(fn ($page) => $page
        ->component('Academic::MatriculaCreate')
        ->has('ofertas', 1)
        ->has('students', 2)
    );

    $student = User::where('email', 'student1@demo.test')->firstOrFail();

    $this->actingAs($staff)
        ->post(demoUrl('/academic/matriculas'), [
            'oferta_academica_id' => $oferta->id,
            'student_id' => $student->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('matriculas', [
        'school_id' => $school->id,
        'oferta_academica_id' => $oferta->id,
        'student_id' => $student->id,
    ]);
});
