<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function tenantUrl(School $school, string $path): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}{$path}";
}

test('valid credentials on the tenant subdomain authenticate and regenerate the session', function () {
    $school = School::factory()->create();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'password' => 'password123',
    ]);
    $user->assignRole('student');

    $response = $this->post(tenantUrl($school, '/login'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('wrong password is rejected without authenticating', function () {
    $school = School::factory()->create();
    $user = User::factory()->create([
        'school_id' => $school->id,
        'password' => 'password123',
    ]);
    $user->assignRole('student');

    $this->post(tenantUrl($school, '/login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('excessive login attempts are throttled', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id, 'password' => 'password123']);
    $user->assignRole('student');

    $maxAttempts = (int) config('security.rate_limits.login_per_minute');

    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->post(tenantUrl($school, '/login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $this->post(tenantUrl($school, '/login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);

    $this->assertGuest();
});

test('logout invalidates the session', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('student');

    $this->actingAs($user)
        ->post(tenantUrl($school, '/logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
