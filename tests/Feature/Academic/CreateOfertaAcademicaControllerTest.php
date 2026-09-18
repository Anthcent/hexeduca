<?php

use App\Tenancy\Models\School;
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
