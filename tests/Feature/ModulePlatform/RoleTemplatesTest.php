<?php

use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Roles belong to a school (Spatie team = school). Every school starts from
// the same templates and then owns its copies.

function schoolRole(School $school, string $name): Role
{
    return Role::query()->where('team_id', $school->id)->where('name', $name)->firstOrFail();
}

function schoolUser(School $school, string $role): User
{
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole($role);

    return $user;
}

test('a new school gets the template roles with the manifest defaults', function () {
    app(ModuleRegistry::class)->sync();

    $school = School::factory()->create();

    expect(Role::query()->where('team_id', $school->id)->pluck('label', 'name')->all())->toEqual([
        'director' => 'Dirección',
        'academic-control' => 'Control de Estudio',
        'administrative' => 'Administrativo',
        'teacher' => 'Docente',
        'student' => 'Estudiante',
    ]);

    expect(schoolRole($school, 'director')->hasPermissionTo('grades.correction'))->toBeTrue()
        ->and(schoolRole($school, 'academic-control')->hasPermissionTo('users.manage'))->toBeTrue()
        ->and(schoolRole($school, 'administrative')->hasPermissionTo('users.manage'))->toBeTrue()
        ->and(schoolRole($school, 'administrative')->hasPermissionTo('grades.manage'))->toBeFalse()
        ->and(schoolRole($school, 'teacher')->hasPermissionTo('grades.manage'))->toBeTrue()
        ->and(schoolRole($school, 'teacher')->hasPermissionTo('grades.scope.all'))->toBeFalse()
        ->and(schoolRole($school, 'student')->hasPermissionTo('notifications.view'))->toBeTrue();
});

test('a permission created after the school exists reaches its template roles', function () {
    $school = School::factory()->create();

    app(ModuleRegistry::class)->sync();

    expect(schoolRole($school, 'director')->hasPermissionTo('users.manage'))->toBeTrue()
        ->and(schoolRole($school, 'teacher')->hasPermissionTo('grades.manage'))->toBeTrue()
        ->and(schoolRole($school, 'teacher')->hasPermissionTo('users.manage'))->toBeFalse();
});

test('a later sync never gives back a permission the school took away', function () {
    app(ModuleRegistry::class)->sync();
    $school = School::factory()->create();
    schoolRole($school, 'teacher')->revokePermissionTo('grades.manage');

    app(ModuleRegistry::class)->sync();

    expect(schoolRole($school, 'teacher')->hasPermissionTo('grades.manage'))->toBeFalse();
});

test('editing one school\'s role leaves the other schools untouched', function () {
    app(ModuleRegistry::class)->sync();
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $teacherA = schoolUser($schoolA, 'teacher');
    $teacherB = schoolUser($schoolB, 'teacher');

    schoolRole($schoolA, 'teacher')->revokePermissionTo('grades.manage');

    expect($teacherA->fresh()->can('grades.manage'))->toBeFalse()
        ->and($teacherB->fresh()->can('grades.manage'))->toBeTrue()
        ->and($teacherA->roles->first()->team_id)->toBe($schoolA->id)
        ->and($teacherB->roles->first()->team_id)->toBe($schoolB->id);
});

test('a user\'s roles resolve to their own school outside any request', function () {
    app(ModuleRegistry::class)->sync();
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    $users = collect([schoolUser($schoolA, 'director'), schoolUser($schoolB, 'student')])
        ->map(fn (User $user) => $user->id);

    $loaded = User::withoutTenantScope()->with('roles')->whereIn('id', $users)->orderBy('id')->get();

    expect($loaded[0]->roles->pluck('team_id')->all())->toBe([$schoolA->id])
        ->and($loaded[0]->can('users.manage'))->toBeTrue()
        ->and($loaded[1]->roles->pluck('team_id')->all())->toBe([$schoolB->id])
        ->and($loaded[1]->can('users.manage'))->toBeFalse();
});

test('a user without a school cannot hold a role', function () {
    School::factory()->create();
    $landlordUser = User::factory()->create(['school_id' => null]);

    expect(fn () => $landlordUser->assignRole('director'))
        ->toThrow(RoleDoesNotExist::class);
});
