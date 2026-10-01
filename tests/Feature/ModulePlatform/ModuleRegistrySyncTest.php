<?php

use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('sync upserts a modules row per manifest with the right core/maturity/active defaults', function () {
    app(ModuleRegistry::class)->sync();

    $users = ModuleRecord::query()->findOrFail('users');
    expect($users->core)->toBeTrue()
        ->and($users->maturity)->toBe('mature')
        // Core modules have no disable path, so they start active.
        ->and($users->active)->toBeTrue();

    $sections = ModuleRecord::query()->findOrFail('sections');
    expect($sections->core)->toBeFalse()
        ->and($sections->maturity)->toBe('mature')
        ->and($sections->active)->toBeFalse();

    $schedule = ModuleRecord::query()->findOrFail('schedule');
    expect($schedule->maturity)->toBe('skeleton')
        ->and($schedule->active)->toBeFalse();
});

test('sync creates Spatie permissions declared in each manifest', function () {
    app(ModuleRegistry::class)->sync();

    expect(Permission::where('name', 'users.view')->where('guard_name', 'web')->exists())->toBeTrue()
        ->and(Permission::where('name', 'sections.view')->where('guard_name', 'web')->exists())->toBeTrue()
        ->and(Permission::where('name', 'admin.access')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('re-syncing preserves the active state of already-registered modules', function () {
    $registry = app(ModuleRegistry::class);
    $registry->sync();
    $registry->enable('sections');

    $registry->sync();

    expect(ModuleRecord::query()->findOrFail('sections')->active)->toBeTrue();
});

test('every object-form manifest permission declares a label and a known kind', function () {
    foreach (Module::all() as $module) {
        foreach ((array) $module->get('permissions', []) as $permission) {
            if (is_string($permission)) {
                continue;
            }

            expect($permission['label'] ?? null)->not->toBeEmpty("{$permission['name']} has no label")
                ->and(ModuleRegistry::PERMISSION_KINDS)->toContain($permission['kind'] ?? null);
        }
    }
});

test('sync rejects an object-form permission without a valid kind', function () {
    $registry = app(ModuleRegistry::class);

    expect(fn () => (fn () => $this->syncPermission(['name' => 'demo.view', 'label' => 'Ver', 'kind' => 'read']))->call($registry))
        ->toThrow(InvalidArgumentException::class, 'demo.view');

    expect(fn () => (fn () => $this->syncPermission(['name' => 'demo.view', 'kind' => 'view']))->call($registry))
        ->toThrow(InvalidArgumentException::class, 'demo.view');
});
