<?php

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

/**
 * Confirms this change (Gate::policy registration, base Controller gaining
 * AuthorizesRequests, new routes) does not alter the existing
 * `role:director` gate on Academic Offering routes. See spec
 * "Requirement: Existing role:director gate on Academic routes keeps
 * working unchanged".
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(ModulePlatformSeeder::class);
});

function academicOfertaCreateUrl(School $school): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}/academic/ofertas/create";
}

test('director still passes the Academic gate', function () {
    $school = School::factory()->create();
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('director');

    $this->actingAs($staff)
        ->get(academicOfertaCreateUrl($school))
        ->assertOk();
});

test('student is still rejected by the Academic gate with 403', function () {
    $school = School::factory()->create();
    $student = User::factory()->create(['school_id' => $school->id, 'type' => 'student']);
    $student->assignRole('student');

    $this->actingAs($student)
        ->get(academicOfertaCreateUrl($school))
        ->assertForbidden();
});
