<?php

use App\ModulePlatform\Services\ModuleAccess;
use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(ModuleRegistry::class)->sync();
});

test('a core module is always allowed, even without a school', function () {
    expect(app(ModuleAccess::class)->allows('users', null))->toBeTrue();
});

test('an inactive optional module is never allowed, even if entitled', function () {
    $registry = app(ModuleRegistry::class);
    $school = School::factory()->create();
    $registry->enable('sections');
    $registry->entitle('sections', $school);
    $registry->disable('sections');

    expect(app(ModuleAccess::class)->allows('sections', $school))->toBeFalse();
});

test('an active optional module is only allowed for schools entitled to it', function () {
    $registry = app(ModuleRegistry::class);
    $entitledSchool = School::factory()->create();
    $otherSchool = School::factory()->create();

    $registry->enable('sections');
    $registry->entitle('sections', $entitledSchool);

    $access = app(ModuleAccess::class);

    expect($access->allows('sections', $entitledSchool))->toBeTrue()
        ->and($access->allows('sections', $otherSchool))->toBeFalse()
        ->and($access->allows('sections', null))->toBeFalse();
});

test('availableKeys lists core modules plus the school entitled optional ones', function () {
    $registry = app(ModuleRegistry::class);
    $school = School::factory()->create();

    $registry->enable('sections');
    $registry->entitle('sections', $school);
    $registry->enable('academicmoments'); // active but NOT entitled for this school

    $keys = app(ModuleAccess::class)->availableKeys($school);

    expect($keys)->toContain('users')
        ->toContain('admin')
        ->toContain('sections')
        ->not->toContain('academicmoments');
});
