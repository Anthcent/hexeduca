<?php

use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->school = School::factory()->create();
    $this->seed(ModulePlatformSeeder::class);
});

/**
 * A user whose only role is a custom one holding exactly `$permissions`,
 * so access can only come from the permissions, never from a role name.
 *
 * @param  list<string>  $permissions
 */
function userWithPermissions(School $school, array $permissions): User
{
    $role = Role::create(['team_id' => $school->id, 'name' => 'custom-'.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole($role);

    return $user;
}

function schoolUrl(School $school, string $path): string
{
    return 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').$path;
}

test('view grants the listing but not writes', function (string $alias, string $path) {
    $viewer = userWithPermissions($this->school, ["{$alias}.view"]);

    $this->actingAs($viewer)->get(schoolUrl($this->school, $path))->assertOk();
    $this->actingAs($viewer)->post(schoolUrl($this->school, $path), [])->assertForbidden();
})->with([
    ['academiclevels', '/academic-levels'],
    ['academicmoments', '/academic-moments'],
    ['academicperiods', '/academic-periods'],
    ['gradelevels', '/grade-levels'],
    ['sections', '/sections'],
    ['users', '/users'],
]);

test('manage grants the create screens that view does not', function (string $alias, string $path) {
    $viewer = userWithPermissions($this->school, ["{$alias}.view"]);
    $manager = userWithPermissions($this->school, ["{$alias}.view", "{$alias}.manage"]);

    $this->actingAs($viewer)->get(schoolUrl($this->school, $path))->assertForbidden();
    $this->actingAs($manager)->get(schoolUrl($this->school, $path))->assertOk();
})->with([
    ['academicoffers', '/academic-offers/create'],
    ['enrollments', '/enrollments/create'],
    ['users', '/users/create'],
]);

test('a role without the module permissions is forbidden', function (string $path) {
    $outsider = userWithPermissions($this->school, []);

    $this->actingAs($outsider)->get(schoolUrl($this->school, $path))->assertForbidden();
})->with(['/academic-levels', '/academic-periods', '/sections', '/users', '/enrollments/create']);
