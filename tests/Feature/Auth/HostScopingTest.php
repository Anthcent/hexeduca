<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('login, and logout are reachable on a tenant subdomain (not 404)', function () {
    $school = School::factory()->create();
    $baseDomain = config('tenancy.base_domain');
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('student');

    $this->get("http://{$school->subdomain}.{$baseDomain}/login")->assertOk();

    $this->post("http://{$school->subdomain}.{$baseDomain}/login", [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionDoesntHaveErrors('email');

    $this->actingAs($user)
        ->post("http://{$school->subdomain}.{$baseDomain}/logout")
        ->assertRedirect(route('login'));
});

// Regression test: with a Route::domain()-per-host loop, only the
// last-registered landlord host would keep the `register` route name
// (Laravel keeps only the last-registered match for a given name). Using
// RequireLandlordHost middleware on a single route registration avoids
// this entirely — route('register') must always resolve to exactly one
// URL, and RequireLandlordHost must match every configured landlord host
// regardless of casing.
test('multi-value landlord_hosts config with mixed casing matches correctly and register resolves once', function () {
    config(['tenancy.landlord_hosts' => ['Admin.App.com', 'localhost', 'OTHER-ADMIN.app.com']]);

    expect(route('register'))->toBe('http://localhost/register');

    $this->get('http://admin.app.com/register')->assertOk();
    $this->get('http://ADMIN.APP.COM/register')->assertOk();
    $this->get('http://other-admin.app.com/register')->assertOk();
    $this->get('http://OTHER-ADMIN.APP.COM/register')->assertOk();

    $this->get('http://not-a-landlord-host.app.com/register')->assertNotFound();
});
