<?php

use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SuperAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Users\Infrastructure\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('application health endpoint boots successfully', function () {
    $this->get('/up')->assertOk();
});

test('sanctum issues csrf cookies and authenticates a stateful request', function () {
    $this->app->detectEnvironment(fn () => 'staging');
    $this->seed(RoleAndPermissionSeeder::class);

    $csrfResponse = $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');

    // TENANCY_LANDLORD_HOSTS=localhost in phpunit.xml — this request hits
    // the landlord host, where only the super-admin role may authenticate
    // (see AuthController::login / design.md "Landlord-host login
    // restricted to super-admin").
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);
    $user->assignRole('super-admin');
    $token = urldecode($csrfResponse->getCookie('XSRF-TOKEN', false)->getValue());

    $this->withHeaders([
        'Origin' => 'http://localhost',
        'Referer' => 'http://localhost/',
        'X-XSRF-TOKEN' => $token,
    ])->postJson('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertRedirect();

    $this->withHeaders([
        'Origin' => 'http://localhost',
        'Referer' => 'http://localhost/',
    ])->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('login rejects missing and invalid csrf tokens', function () {
    $this->app->detectEnvironment(fn () => 'staging');
    User::factory()->create(['email' => 'csrf@example.test']);

    $this->postJson('/login', [
        'email' => 'csrf@example.test',
        'password' => 'password',
    ])->assertStatus(419);

    $this->withHeader('X-XSRF-TOKEN', 'invalid-token')->postJson('/login', [
        'email' => 'csrf@example.test',
        'password' => 'password',
    ])->assertStatus(419);
});

test('role seeder creates only the four baseline roles without permissions', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    expect(Role::query()->orderBy('name')->pluck('name')->all())->toBe([
        'staff/admin',
        'student',
        'super-admin',
        'teacher',
    ])->and(Permission::query()->count())->toBe(0);
});

test('super-admin seeder fails closed without explicit credentials', function () {
    config()->set('bootstrap.super_admin', [
        'name' => 'Super Admin',
        'email' => null,
        'password' => null,
    ]);
    $this->seed(RoleAndPermissionSeeder::class);

    expect(fn () => $this->seed(SuperAdminUserSeeder::class))
        ->toThrow(RuntimeException::class, 'Invalid super-admin bootstrap configuration')
        ->and(User::query()->count())->toBe(0);
});

test('super-admin seeder uses explicit strong bootstrap credentials', function () {
    config()->set('bootstrap.super_admin', [
        'name' => 'Foundation Administrator',
        'email' => 'foundation-admin@example.test',
        'password' => 'Foundation!Admin2026',
    ]);
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(SuperAdminUserSeeder::class);

    $user = User::query()->where('email', 'foundation-admin@example.test')->sole();

    expect($user->name)->toBe('Foundation Administrator')
        ->and(Hash::check('Foundation!Admin2026', $user->password))->toBeTrue()
        ->and($user->hasRole('super-admin'))->toBeTrue();
});

test('super-admin seeder rejects weak bootstrap passwords', function () {
    config()->set('bootstrap.super_admin', [
        'name' => 'Super Admin',
        'email' => 'admin@example.test',
        'password' => 'password',
    ]);
    $this->seed(RoleAndPermissionSeeder::class);

    expect(fn () => $this->seed(SuperAdminUserSeeder::class))
        ->toThrow(RuntimeException::class, 'Invalid super-admin bootstrap configuration')
        ->and(User::query()->count())->toBe(0);
});

test('root route renders the inertia base page', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});
