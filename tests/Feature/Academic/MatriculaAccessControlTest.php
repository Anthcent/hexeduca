<?php

use App\Tenancy\Models\School;
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

test('an authenticated user without the staff/admin role is rejected with 403', function () {
    $school = School::factory()->create();
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');

    $this->actingAs($student)
        ->get(matriculaCreateUrl($school))
        ->assertForbidden();
});
