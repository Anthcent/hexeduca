<?php

use App\Tenancy\Models\School;
use App\Tenancy\Scopes\TenantScope;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// TENANCY_LANDLORD_HOSTS=localhost in phpunit.xml, and Laravel's testing
// HTTP client defaults to host "localhost" — so a plain path request
// without an explicit host hits the landlord host.
test('valid registration creates a student in the chosen school without authenticating', function () {
    $school = School::factory()->create(['subdomain' => 'schoola']);

    $response = $this->post('/register', [
        'school_id' => $school->id,
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::withoutGlobalScope(TenantScope::class)
        ->where('email', 'newuser@example.com')
        ->first();

    expect($user)->not->toBeNull();
    expect($user->school_id)->toBe($school->id);
    expect($user->hasRole('student'))->toBeTrue();
    expect($user->getRoleNames())->toHaveCount(1);

    $response->assertRedirect('http://schoola.'.config('tenancy.base_domain').'/login?registered=1');
    $this->assertGuest();
});

test('registration does not establish a session on the landlord host', function () {
    $school = School::factory()->create(['subdomain' => 'schoolb']);

    $this->post('/register', [
        'school_id' => $school->id,
        'name' => 'Another User',
        'email' => 'another@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    expect(Auth::check())->toBeFalse();
    $this->assertGuest();
});

test('registration is unreachable from a tenant subdomain', function () {
    $school = School::factory()->create(['subdomain' => 'schoolc']);
    $baseDomain = config('tenancy.base_domain');

    $this->get("http://{$school->subdomain}.{$baseDomain}/register")->assertNotFound();

    $this->post("http://{$school->subdomain}.{$baseDomain}/register", [
        'school_id' => $school->id,
        'name' => 'Blocked User',
        'email' => 'blocked@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
});

test('duplicate email is rejected', function () {
    $school = School::factory()->create();
    User::factory()->create(['school_id' => $school->id, 'email' => 'existing@example.com']);

    $this->post('/register', [
        'school_id' => $school->id,
        'name' => 'Dup User',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');

    expect(User::withoutGlobalScope(TenantScope::class)->where('email', 'existing@example.com')->count())->toBe(1);
});

test('missing or invalid school selection is rejected', function () {
    $this->post('/register', [
        'name' => 'No School',
        'email' => 'noschool@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('school_id');

    $inactiveSchool = School::factory()->inactive()->create();

    $this->post('/register', [
        'school_id' => $inactiveSchool->id,
        'name' => 'Inactive School',
        'email' => 'inactiveschool@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('school_id');

    $this->assertDatabaseMissing('users', ['email' => 'noschool@example.com']);
    $this->assertDatabaseMissing('users', ['email' => 'inactiveschool@example.com']);
});
