<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// `tenantUrl()` is defined globally in tests/Feature/Auth/LoginLogoutTest.php.

function userWithRole(?School $school, string $role): User
{
    $user = User::factory()->create(['school_id' => $school?->id]);
    $user->assignRole($role);

    return $user;
}

function newUserPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'role' => 'teacher',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ], $overrides);
}

function landlordUrl(string $path): string
{
    return 'http://'.config('tenancy.landlord_hosts')[0].$path;
}

test('staff/admin sees the create form with only assignable roles', function () {
    $school = School::factory()->create();
    $admin = userWithRole($school, 'staff/admin');

    $this->actingAs($admin)
        ->get(tenantUrl($school, '/users/create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users::Create', false)
            ->where('roles', ['student', 'teacher', 'staff/admin'])
            ->where('school.id', $school->id)
            ->where('schools', []));
});

test('staff/admin creates a user in their own school, ignoring a submitted school_id', function () {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $admin = userWithRole($school, 'staff/admin');

    $response = $this->actingAs($admin)
        ->post(tenantUrl($school, '/users'), newUserPayload(['school_id' => $otherSchool->id]));

    $created = User::withoutTenantScope()->where('email', 'ada@example.test')->sole();
    $response->assertRedirect(route('users.show', $created->id))
        ->assertSessionHasNoErrors();

    expect($created->school_id)->toBe($school->id)
        ->and($created->name)->toBe('Ada Lovelace')
        ->and($created->getRoleNames()->all())->toBe(['teacher'])
        ->and(Hash::check('correct-horse-battery', $created->password))->toBeTrue();

    $payload = json_decode(DB::table('integration_outbox_events')
        ->where('event_name', 'user.created')
        ->where('aggregate_id', (string) $created->id)
        ->value('payload'), true);
    expect($payload['roles'])->toBe(['teacher'])
        ->and($payload['schoolId'])->toBe($school->id);
});

test('store rejects invalid input', function (array $overrides, array $errors) {
    $school = School::factory()->create();
    $admin = userWithRole($school, 'staff/admin');
    User::factory()->create(['email' => 'taken@example.test', 'school_id' => School::factory()->create()->id]);
    $before = User::withoutTenantScope()->count();

    $this->actingAs($admin)
        ->post(tenantUrl($school, '/users'), newUserPayload($overrides))
        ->assertSessionHasErrors($errors);

    expect(User::withoutTenantScope()->count())->toBe($before);
})->with([
    'missing name' => [['name' => ''], ['name']],
    'invalid email' => [['email' => 'not-an-email'], ['email']],
    'email taken in another school' => [['email' => 'taken@example.test'], ['email']],
    'unknown role' => [['role' => 'janitor'], ['role']],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], ['password']],
    'password mismatch' => [['password_confirmation' => 'something-else'], ['password']],
]);

test('nobody can create a super-admin, not even a super-admin', function (string $actorRole) {
    $school = School::factory()->create();
    $actor = userWithRole($actorRole === 'super-admin' ? null : $school, $actorRole);

    $this->actingAs($actor)
        ->post(tenantUrl($school, '/users'), newUserPayload(['role' => 'super-admin']))
        ->assertSessionHasErrors(['role']);

    expect(User::withoutTenantScope()->where('email', 'ada@example.test')->exists())->toBeFalse();
})->with(['staff/admin', 'super-admin']);

test('staff/admin authenticated on another school host cannot create users there', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $admin = userWithRole($schoolA, 'staff/admin');

    $this->actingAs($admin)
        ->post(tenantUrl($schoolB, '/users'), newUserPayload())
        ->assertForbidden();

    expect(User::withoutTenantScope()->where('email', 'ada@example.test')->exists())->toBeFalse();
});

test('super-admin on the landlord host picks the school', function () {
    $school = School::factory()->create();
    $superAdmin = userWithRole(null, 'super-admin');

    $this->actingAs($superAdmin)
        ->post(landlordUrl('/users'), newUserPayload())
        ->assertSessionHasErrors(['school_id']);

    $this->actingAs($superAdmin)
        ->post(landlordUrl('/users'), newUserPayload(['school_id' => $school->id]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(User::withoutTenantScope()->where('email', 'ada@example.test')->value('school_id'))->toBe($school->id);
});

test('staff/admin views a user of their own school', function () {
    $school = School::factory()->create(['name' => 'Colegio Demo']);
    $admin = userWithRole($school, 'staff/admin');
    $target = userWithRole($school, 'student');

    $this->actingAs($admin)
        ->get(tenantUrl($school, "/users/{$target->id}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users::Show', false)
            ->where('user.id', $target->id)
            ->where('user.email', $target->email)
            ->where('user.role', 'student')
            ->where('user.school', 'Colegio Demo')
            ->has('user.created_at')
            ->where('can.edit', true)
            ->where('can.delete', true));
});

test('show and destroy of a user from another school are 404', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $admin = userWithRole($schoolA, 'staff/admin');
    $target = userWithRole($schoolB, 'student');

    $this->actingAs($admin)
        ->get(tenantUrl($schoolA, "/users/{$target->id}"))
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete(tenantUrl($schoolA, "/users/{$target->id}"))
        ->assertNotFound();

    expect(User::withoutTenantScope()->whereKey($target->id)->exists())->toBeTrue();
});

test('staff/admin deletes a user of their own school and projections are told the roles are gone', function () {
    $school = School::factory()->create();
    $admin = userWithRole($school, 'staff/admin');
    $target = userWithRole($school, 'teacher');

    $this->actingAs($admin)
        ->delete(tenantUrl($school, "/users/{$target->id}"))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect(User::withoutTenantScope()->whereKey($target->id)->exists())->toBeFalse();

    $payload = json_decode(DB::table('integration_outbox_events')
        ->where('event_name', 'user.updated')
        ->where('aggregate_id', (string) $target->id)
        ->value('payload'), true);
    expect($payload['roles'])->toBe([])
        ->and($payload['version'])->toBe(2);
});

test('staff/admin cannot delete themselves', function () {
    $school = School::factory()->create();
    $admin = userWithRole($school, 'staff/admin');

    $this->actingAs($admin)
        ->delete(tenantUrl($school, "/users/{$admin->id}"))
        ->assertForbidden();

    expect(User::withoutTenantScope()->whereKey($admin->id)->exists())->toBeTrue();
});

test('a super-admin cannot be deleted', function () {
    $school = School::factory()->create();
    $admin = userWithRole($school, 'staff/admin');
    $superAdminInSchool = userWithRole($school, 'super-admin');

    $this->actingAs($admin)
        ->delete(tenantUrl($school, "/users/{$superAdminInSchool->id}"))
        ->assertForbidden();

    expect(User::withoutTenantScope()->whereKey($superAdminInSchool->id)->exists())->toBeTrue();
});

test('teachers and students get 403 on every user management route', function (string $role) {
    $school = School::factory()->create();
    $actor = userWithRole($school, $role);
    $target = userWithRole($school, 'student');

    $this->actingAs($actor)->get(tenantUrl($school, '/users/create'))->assertForbidden();
    $this->actingAs($actor)->post(tenantUrl($school, '/users'), newUserPayload())->assertForbidden();
    $this->actingAs($actor)->get(tenantUrl($school, "/users/{$target->id}"))->assertForbidden();
    $this->actingAs($actor)->delete(tenantUrl($school, "/users/{$target->id}"))->assertForbidden();

    expect(User::withoutTenantScope()->whereKey($target->id)->exists())->toBeTrue()
        ->and(User::withoutTenantScope()->where('email', 'ada@example.test')->exists())->toBeFalse();
})->with(['teacher', 'student']);
