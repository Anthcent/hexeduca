<?php

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function matriculaCreateUrl(School $school): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}/academic/matriculas/create";
}

function matriculaStoreUrl(School $school): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}/academic/matriculas";
}

test('guest is redirected to login when requesting the matricula screen', function () {
    $school = School::factory()->create();

    $this->get(matriculaCreateUrl($school))->assertRedirect(route('login'));
});

test('guest is redirected to login when submitting a matricula', function () {
    $school = School::factory()->create();

    $this->post(matriculaStoreUrl($school), [])->assertRedirect(route('login'));
});

test('an authenticated user without academic.manage is rejected with 403', function () {
    $school = School::factory()->create();
    // The module gate runs first (404), so the module must be available.
    $this->seed(ModulePlatformSeeder::class);
    $student = User::factory()->create(['school_id' => $school->id, 'type' => 'student']);
    $student->assignRole('student');

    $this->actingAs($student)
        ->get(matriculaCreateUrl($school))
        ->assertForbidden();
});
