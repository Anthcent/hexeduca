<?php

use App\ModulePlatform\Exceptions\ModuleCoreException;
use App\ModulePlatform\Exceptions\ModuleDependencyException;
use App\ModulePlatform\Exceptions\ModuleNotFoundException;
use App\ModulePlatform\Exceptions\ModuleNotReadyException;
use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(ModuleRegistry::class)->sync();
});

test('enabling an unknown module key throws', function () {
    app(ModuleRegistry::class)->enable('does-not-exist');
})->throws(ModuleNotFoundException::class);

test('enabling a skeleton module throws', function () {
    // Schedule is manifest maturity: skeleton (see Modules/Schedule/module.json).
    app(ModuleRegistry::class)->enable('schedule');
})->throws(ModuleNotReadyException::class);

test('enabling a module whose dependency is not active yet throws', function () {
    // GradeLevels depends on AcademicLevels (see Modules/GradeLevels/module.json).
    app(ModuleRegistry::class)->enable('gradelevels');
})->throws(ModuleDependencyException::class);

test('enabling a module succeeds once its dependency is active', function () {
    $registry = app(ModuleRegistry::class);

    $registry->enable('academiclevels');
    $registry->enable('gradelevels');

    expect(ModuleRecord::query()->findOrFail('gradelevels')->active)->toBeTrue();
});

test('disabling a core module throws', function () {
    app(ModuleRegistry::class)->disable('users');
})->throws(ModuleCoreException::class);

test('disabling a module with active dependents throws', function () {
    $registry = app(ModuleRegistry::class);
    $registry->enable('academiclevels');
    $registry->enable('gradelevels');

    $registry->disable('academiclevels');
})->throws(ModuleDependencyException::class);

test('disabling a module with no active dependents succeeds', function () {
    $registry = app(ModuleRegistry::class);
    $registry->enable('sections');

    $registry->disable('sections');

    expect(ModuleRecord::query()->findOrFail('sections')->active)->toBeFalse();
});
