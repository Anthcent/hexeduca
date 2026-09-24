<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Generators\ModuleGenerator;
use Symfony\Component\Filesystem\Filesystem;
use Tests\Architecture\Support\ModuleArchitectureValidator;

/**
 * R1.2/R1.3: exercises the real, configured generator pipeline
 * (config/modules.php `generator`/`stubs` + stubs/modules/*.stub) the same
 * way `php artisan make:project-module` does, but calling
 * Nwidart\Modules\Generators\ModuleGenerator directly instead of going
 * through `module:make`'s console command. `module:make` shells out to
 * `composer dump-autoload` after generating, which is unrelated to what
 * this test verifies and is too slow/environment-dependent for the test
 * suite (observed 3+ minutes on this project). See
 * sdd/module-developer-platform/apply-progress for the measurement.
 */
function generatedModulePath(string $name): string
{
    return base_path('Modules/'.$name);
}

function generateProjectModuleForTest(string $name): int
{
    Artisan::command('test:generate-project-module-'.strtolower($name).' {name}', function (string $name) {
        /** @var Command $this */
        return (new ModuleGenerator($name))
            ->setFilesystem($this->laravel['files'])
            ->setModule($this->laravel['modules'])
            ->setConfig($this->laravel['config'])
            ->setActivator($this->laravel[ActivatorInterface::class])
            ->setConsole($this)
            ->setComponent($this->components)
            ->setAuthor(null, null)
            ->setType('web')
            ->setInertia(true)
            ->setActive(false)
            ->generate();
    });

    return Artisan::call('test:generate-project-module-'.strtolower($name), ['name' => $name]);
}

afterEach(function () {
    $path = generatedModulePath('GenTestModule');

    if (is_dir($path)) {
        (new Filesystem)->remove($path);
    }

    // The file activator records every generated module's active flag in
    // modules_statuses.json regardless of directory cleanup. Strip the
    // test module's entry so repeated runs don't leave that file dirty.
    $statusesPath = base_path('modules_statuses.json');
    if (is_file($statusesPath)) {
        $statuses = json_decode(file_get_contents($statusesPath), true) ?? [];
        unset($statuses['GenTestModule']);
        file_put_contents($statusesPath, json_encode($statuses, JSON_PRETTY_PRINT));
    }
});

test('the project module generator scaffolds the standard hexagonal tree', function () {
    $exitCode = generateProjectModuleForTest('GenTestModule');

    expect($exitCode)->toBe(0);

    $root = generatedModulePath('GenTestModule');

    foreach ([
        'Domain/Entities',
        'Domain/Repositories',
        'Application/UseCases',
        'Application/DTOs',
        'Infrastructure/Models',
        'Infrastructure/Persistence',
        'Infrastructure/Providers',
        'Infrastructure/Http/Controllers',
        'Infrastructure/Database/Migrations',
        'Infrastructure/Database/Seeders',
        'Public/Contracts',
        'Tests/Unit',
        'Tests/Feature',
        'routes',
        'config',
    ] as $expectedDir) {
        expect(is_dir($root.'/'.$expectedDir))->toBeTrue("Expected directory {$expectedDir} to exist.");
    }

    expect(is_file($root.'/module.json'))->toBeTrue()
        ->and(is_file($root.'/MODULE.md'))->toBeTrue()
        ->and(is_file($root.'/routes/web.php'))->toBeTrue()
        ->and(is_file($root.'/routes/api.php'))->toBeTrue()
        ->and(is_file($root.'/Infrastructure/Providers/GenTestModuleServiceProvider.php'))->toBeTrue();
});

test('the generated module.json carries manifest v1 fields', function () {
    generateProjectModuleForTest('GenTestModule');

    $manifest = json_decode(file_get_contents(generatedModulePath('GenTestModule').'/module.json'), true);

    expect($manifest)->toHaveKeys(['core', 'maturity', 'dependencies', 'permissions'])
        ->and($manifest['core'])->toBeFalse()
        ->and($manifest['maturity'])->toBe('skeleton')
        ->and($manifest['dependencies'])->toBe([])
        ->and($manifest['permissions'])->toBe([]);
});

test('the generated routes are guarded with auth and the module gate', function () {
    generateProjectModuleForTest('GenTestModule');

    $root = generatedModulePath('GenTestModule');
    $web = file_get_contents($root.'/routes/web.php');
    $api = file_get_contents($root.'/routes/api.php');

    expect($web)->toContain("'auth'")
        ->and($web)->toContain('module:gentestmodule')
        ->and($api)->toContain("'auth:sanctum'")
        ->and($api)->toContain('module:gentestmodule');
});

test('the architecture suite stays green on a generated module', function () {
    generateProjectModuleForTest('GenTestModule');

    $validator = new ModuleArchitectureValidator(base_path());

    $boundaryViolations = array_filter(
        $validator->boundaryViolations(),
        fn (string $violation): bool => str_contains($violation, 'GenTestModule'),
    );
    $manifestViolations = array_filter(
        $validator->manifestDependencyViolations(),
        fn (string $violation): bool => str_contains($violation, 'GenTestModule'),
    );
    $unguardedRoutes = array_filter(
        $validator->unguardedRouteViolations(),
        fn (string $violation): bool => str_contains($violation, 'GenTestModule'),
    );
    $activatorWrites = array_filter(
        $validator->httpFileActivatorWriteViolations(),
        fn (string $violation): bool => str_contains($violation, 'GenTestModule'),
    );

    expect($boundaryViolations)->toBe([])
        ->and($manifestViolations)->toBe([])
        ->and($unguardedRoutes)->toBe([])
        ->and($activatorWrites)->toBe([]);
});
