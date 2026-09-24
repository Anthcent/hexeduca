<?php

use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    app(ModuleRegistry::class)->sync();
});

function actingSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    return $admin;
}

test('a non-super-admin gets 403 on the modules index', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff/admin');

    $this->actingAs($staff)
        ->get(route('admin.modules.index'))
        ->assertForbidden();
});

test('a guest is redirected away from the modules index', function () {
    $this->get(route('admin.modules.index'))->assertRedirect();
});

test('the super-admin sees the modules index with dependency data', function () {
    $admin = actingSuperAdmin();

    $this->actingAs($admin)
        ->get(route('admin.modules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // shouldExist: false — the Inertia test page-finder only looks
            // under resource_path('js/pages'), not module Resources/js/Pages
            // directories (same as the pre-existing Admin::Schools page).
            ->component('Admin::Modules', false)
            ->has('modules')
            ->has('schools')
        );
});

test('the super-admin can sync manifests from the admin page', function () {
    $admin = actingSuperAdmin();
    ModuleRecord::query()->delete();

    $this->actingAs($admin)
        ->post(route('admin.modules.sync'))
        ->assertRedirect();

    expect(ModuleRecord::query()->count())->toBeGreaterThan(0);
});

test('the super-admin can toggle a module active', function () {
    $admin = actingSuperAdmin();
    app(ModuleRegistry::class)->enable('academiclevels');

    $this->actingAs($admin)
        ->post(route('admin.modules.toggle-active', 'academiclevels'))
        ->assertRedirect();

    expect(ModuleRecord::query()->findOrFail('academiclevels')->active)->toBeFalse();
});

test('toggling active a module whose dependency is missing surfaces a flash error, not a 500', function () {
    $admin = actingSuperAdmin();

    // gradelevels depends on academiclevels, which is not active.
    $this->actingAs($admin)
        ->post(route('admin.modules.toggle-active', 'gradelevels'))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(ModuleRecord::query()->findOrFail('gradelevels')->active)->toBeFalse();
});

test('the super-admin can toggle a school entitlement', function () {
    $admin = actingSuperAdmin();
    $registry = app(ModuleRegistry::class);
    $school = School::factory()->create();
    $registry->enable('sections');
    $registry->revoke('sections', $school);

    $this->actingAs($admin)
        ->post(route('admin.modules.toggle-entitlement', ['sections', $school->id]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->post(route('admin.modules.toggle-entitlement', ['sections', $school->id]))
        ->assertRedirect()
        ->assertSessionHas('success');
});
