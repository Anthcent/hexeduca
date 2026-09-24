<?php

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

function sectionsIndexUrl(School $school): string
{
    $baseDomain = config('tenancy.base_domain');

    return "http://{$school->subdomain}.{$baseDomain}/sections";
}

test('the module gate returns 404 when the module is not active', function () {
    $school = School::factory()->create();
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');

    // Sections is registered but not enabled and the school isn't entitled.
    $this->actingAs($staff)
        ->get(sectionsIndexUrl($school))
        ->assertNotFound();
});

test('the module gate returns 404 when the module is active but the school is not entitled', function () {
    // The school must exist BEFORE the module is enabled: SchoolCacheObserver
    // auto-entitles newly created schools to whatever is already active at
    // creation time (see R2.6), so this scenario only occurs for a school
    // that predates the module's activation.
    $school = School::factory()->create();
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');

    app(ModuleRegistry::class)->enable('sections');

    $this->actingAs($staff)
        ->get(sectionsIndexUrl($school))
        ->assertNotFound();
});

test('the module gate allows the request when active and the school is entitled', function () {
    $registry = app(ModuleRegistry::class);
    $school = School::factory()->create();
    $registry->enable('sections');
    $registry->entitle('sections', $school);

    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');

    $this->actingAs($staff)
        ->get(sectionsIndexUrl($school))
        ->assertOk();
});

test('per-school isolation: a different school entitled to sections does not unlock it for this one', function () {
    $registry = app(ModuleRegistry::class);
    // Both schools created before enabling, so neither is auto-entitled;
    // only $entitledSchool gets an explicit entitlement below.
    $entitledSchool = School::factory()->create();
    $otherSchool = School::factory()->create();

    $registry->enable('sections');
    $registry->entitle('sections', $entitledSchool);

    $staff = User::factory()->create(['school_id' => $otherSchool->id]);
    $staff->assignRole('staff/admin');

    $this->actingAs($staff)
        ->get(sectionsIndexUrl($otherSchool))
        ->assertNotFound();
});
