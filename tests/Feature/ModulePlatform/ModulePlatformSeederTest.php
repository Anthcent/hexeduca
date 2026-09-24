<?php

use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Models\SchoolModule;
use App\Tenancy\Models\School;
use Database\Seeders\ModulePlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeding activates mature optional modules and entitles existing schools to them', function () {
    $school = School::factory()->create();

    $this->seed(ModulePlatformSeeder::class);

    // Sections has no dependencies and is mature/optional -> should be active.
    expect(ModuleRecord::query()->findOrFail('sections')->active)->toBeTrue()
        // GradeLevels depends on AcademicLevels; the fixed-point loop must
        // still activate it once its dependency is active.
        ->and(ModuleRecord::query()->findOrFail('gradelevels')->active)->toBeTrue()
        // Skeleton modules never get auto-enabled.
        ->and(ModuleRecord::query()->findOrFail('files')->active)->toBeFalse()
        // Core modules stay active regardless.
        ->and(ModuleRecord::query()->findOrFail('users')->active)->toBeTrue();

    expect(SchoolModule::query()
        ->where('school_id', $school->id)
        ->where('module_key', 'sections')
        ->where('enabled', true)
        ->exists())->toBeTrue();
});

test('seeding is idempotent for an existing school', function () {
    $school = School::factory()->create();

    $this->seed(ModulePlatformSeeder::class);
    $this->seed(ModulePlatformSeeder::class);

    expect(SchoolModule::query()
        ->where('school_id', $school->id)
        ->where('module_key', 'sections')
        ->count())->toBe(1);
});
