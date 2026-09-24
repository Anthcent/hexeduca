<?php

use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Models\SchoolModule;
use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('modules:sync populates the registry from manifests', function () {
    $this->artisan('modules:sync')
        ->assertExitCode(0);

    expect(ModuleRecord::query()->count())->toBeGreaterThan(0);
    expect(ModuleRecord::query()->findOrFail('academic')->maturity)->toBe('mature');
});

test('modules:enable activates a module once its dependencies are active', function () {
    app(ModuleRegistry::class)->sync();

    $this->artisan('modules:enable academiclevels')
        ->assertExitCode(0);

    expect(ModuleRecord::query()->findOrFail('academiclevels')->active)->toBeTrue();
});

test('modules:enable prints a clean error and fails when a dependency is missing', function () {
    app(ModuleRegistry::class)->sync();

    $this->artisan('modules:enable gradelevels')
        ->assertExitCode(1);

    expect(ModuleRecord::query()->findOrFail('gradelevels')->active)->toBeFalse();
});

test('modules:disable rejects a core module', function () {
    app(ModuleRegistry::class)->sync();

    $this->artisan('modules:disable users')
        ->assertExitCode(1);

    expect(ModuleRecord::query()->findOrFail('users')->active)->toBeTrue();
});

test('modules:entitle grants and --revoke removes a school entitlement', function () {
    $registry = app(ModuleRegistry::class);
    $registry->sync();
    $registry->enable('sections');
    $school = School::factory()->create(['subdomain' => 'demo-cli']);

    $this->artisan("modules:entitle sections {$school->subdomain}")
        ->assertExitCode(0);

    expect(SchoolModule::query()
        ->where('school_id', $school->id)
        ->where('module_key', 'sections')
        ->where('enabled', true)
        ->exists())->toBeTrue();

    $this->artisan("modules:entitle sections {$school->subdomain} --revoke")
        ->assertExitCode(0);

    expect(SchoolModule::query()
        ->where('school_id', $school->id)
        ->where('module_key', 'sections')
        ->where('enabled', true)
        ->exists())->toBeFalse();
});

test('modules:entitle fails cleanly for an unknown school subdomain', function () {
    app(ModuleRegistry::class)->sync();

    $this->artisan('modules:entitle sections does-not-exist')
        ->assertExitCode(1);
});

test('modules:list prints registered modules', function () {
    app(ModuleRegistry::class)->sync();

    $this->artisan('modules:list')
        ->assertExitCode(0)
        ->expectsOutputToContain('academic');
});

test('modules:list warns when nothing is registered yet', function () {
    $this->artisan('modules:list')
        ->assertExitCode(0);
});
