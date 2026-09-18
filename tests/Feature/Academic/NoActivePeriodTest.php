<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\Seccion;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function noActivePeriodUrl(School $school, string $path): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}{$path}";
}

test('the create oferta screen reports no active period without raising an exception', function () {
    $school = School::factory()->create();
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');

    $this->actingAs($staff)
        ->get(noActivePeriodUrl($school, '/academic/ofertas/create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academic::OfertaCreate')
            ->where('hasActivePeriodo', false)
        );
});

test('submitting an oferta with no active period surfaces a session error instead of crashing', function () {
    $school = School::factory()->create();
    $grado = Grado::factory()->create(['school_id' => $school->id]);
    $seccion = Seccion::factory()->create(['school_id' => $school->id]);
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');

    $this->actingAs($staff)
        ->post(noActivePeriodUrl($school, '/academic/ofertas'), [
            'grado_id' => $grado->id,
            'seccion_id' => $seccion->id,
            'capacity' => 10,
        ])
        ->assertSessionHasErrors('grado_id');
});
