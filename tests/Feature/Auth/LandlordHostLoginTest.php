<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// TENANCY_LANDLORD_HOSTS=localhost in phpunit.xml, and Laravel's testing
// HTTP client defaults to host "localhost" — so a plain path request
// without an explicit host hits the landlord host.
test('non-super-admin valid credentials on the landlord host are rejected', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id, 'password' => 'password123']);
    $user->assignRole('staff/admin');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('seeded super-admin on the landlord host is authenticated', function () {
    $superAdmin = User::factory()->create(['school_id' => null, 'password' => 'password123']);
    $superAdmin->assignRole('super-admin');

    $response = $this->post('/login', [
        'email' => $superAdmin->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($superAdmin);
});

test('super-admin login on a tenant subdomain fails naturally via TenantScope', function () {
    $school = School::factory()->create();
    $superAdmin = User::factory()->create(['school_id' => null, 'password' => 'password123']);
    $superAdmin->assignRole('super-admin');

    $baseDomain = config('tenancy.base_domain');

    $this->post("http://{$school->subdomain}.{$baseDomain}/login", [
        'email' => $superAdmin->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
