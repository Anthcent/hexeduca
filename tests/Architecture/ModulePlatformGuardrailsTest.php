<?php

use Symfony\Component\Filesystem\Filesystem;
use Tests\Architecture\Support\ModuleArchitectureValidator;

function architectureFixture(array $files): string
{
    $root = sys_get_temp_dir().'/hexeduca-architecture-'.bin2hex(random_bytes(6));

    $filesystem = new Filesystem;
    foreach ($files as $path => $contents) {
        $filesystem->dumpFile($root.'/'.$path, $contents);
    }

    return $root;
}

function removeArchitectureFixture(string $root): void
{
    (new Filesystem)->remove($root);
}

test('module references do not add sibling-internal debt and Public APIs are declared in manifests', function () {
    $validator = new ModuleArchitectureValidator(dirname(__DIR__, 2));

    expect($validator->boundaryViolations())->toBe([
        'Modules/Academic/Infrastructure/Database/Seeders/AcademicDatabaseSeeder.php -> Modules\\Users\\Infrastructure\\Models\\User',
        'Modules/Academic/Infrastructure/Http/Controllers/MatriculaController.php -> Modules\\Users\\Infrastructure\\Models\\User',
        'Modules/Academic/Infrastructure/Http/Controllers/OfertaAcademicaController.php -> Modules\\Users\\Infrastructure\\Models\\User',
        'Modules/Academic/Infrastructure/Http/Controllers/PeriodoAcademicoController.php -> Modules\\AcademicPeriods\\Application\\UseCases\\ActivateAcademicPeriod',
        'Modules/Academic/Infrastructure/Http/Controllers/PeriodoAcademicoController.php -> Modules\\AcademicPeriods\\Infrastructure\\Models\\AcademicPeriod',
        'Modules/Academic/Infrastructure/Models/Matricula.php -> Modules\\Users\\Infrastructure\\Models\\User',
        'Modules/Academic/Infrastructure/Models/OfertaAcademica.php -> Modules\\Users\\Infrastructure\\Models\\User',
        'Modules/AcademicMoments/Infrastructure/Models/AcademicMoment.php -> Modules\\AcademicPeriods\\Infrastructure\\Models\\AcademicPeriod',
        'Modules/AcademicOffers/Infrastructure/Models/AcademicOffer.php -> Modules\\AcademicPeriods\\Infrastructure\\Models\\AcademicPeriod',
        'Modules/Grades/Infrastructure/Policies/GradePolicy.php -> Modules\\Users\\Infrastructure\\Models\\User',
    ])
        ->and($validator->manifestDependencyViolations())->toBe([]);
});

test('boundary diagnostics catch imports, fully qualified references, and undeclared dependencies', function () {
    $root = architectureFixture([
        'Modules/Alpha/module.json' => json_encode(['name' => 'Alpha', 'dependencies' => []]),
        'Modules/Alpha/Import.php' => '<?php use Modules\\Beta\\Domain\\Secret;',
        'Modules/Alpha/Reference.php' => '<?php $type = Modules\\Gamma\\Public\\Contract::class;',
        'Modules/Beta/module.json' => json_encode(['name' => 'Beta', 'dependencies' => []]),
        'Modules/Gamma/module.json' => json_encode(['name' => 'Gamma', 'dependencies' => []]),
    ]);

    try {
        $validator = new ModuleArchitectureValidator($root);

        expect($validator->boundaryViolations())->toHaveCount(1)
            ->and($validator->boundaryViolations()[0])->toContain('Alpha/Import.php -> Modules\\Beta\\Domain\\Secret')
            ->and($validator->manifestDependencyViolations())->toHaveCount(1)
            ->and($validator->manifestDependencyViolations()[0])->toContain('Alpha/Reference.php -> Gamma');
    } finally {
        removeArchitectureFixture($root);
    }
});

test('module ingress is authenticated and HTTP code cannot write deployment state', function () {
    $validator = new ModuleArchitectureValidator(dirname(__DIR__, 2));

    expect($validator->unguardedRouteViolations())->toBe([])
        ->and($validator->httpFileActivatorWriteViolations())->toBe([]);
});

test('ingress diagnostics reject unguarded routes and HTTP deployment-state writes', function () {
    $root = architectureFixture([
        'Modules/Alpha/routes/web.php' => <<<'PHP'
            <?php
            use Illuminate\Support\Facades\Route;
            // middleware('auth') in a comment grants nothing.
            Route::middleware('auth')->group(function () {
                Route::prefix('nested')->group(function () {
                    Route::match(['get', 'post'], '/guarded', fn () => 'guarded');
                });
            });
            Route::any('/unguarded', fn () => 'unguarded');
            PHP,
        'Modules/Beta/routes/api.php' => "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::middleware(['auth:sanctum'])->apiResource('items', 'ItemController');",
        'Modules/Users/routes/web.php' => <<<'PHP'
            <?php
            use Illuminate\Support\Facades\Route;
            Route::get('/login', fn () => 'login');
            Route::post('/register', fn () => 'register');
            Route::get('/login/admin', fn () => 'not-public-auth');
            PHP,
        'app/Http/Controllers/RenamedController.php' => '<?php $deploymentSwitch->enable($module);',
        'app/Http/Controllers/StaticController.php' => '<?php Module::disable($module);',
        'app/Http/Controllers/ContainerController.php' => '<?php app(\Nwidart\Modules\Activators\FileActivator::class);',
        'app/Http/Controllers/DynamicEnableController.php' => <<<'PHP'
            <?php
            $resolvedActivator = app('modules.activator');
            $activatorAlias = $resolvedActivator;
            $prefix = 'en';
            $suffix = 'able';
            $operation = $prefix.$suffix;
            $activatorAlias->{$operation}($module);
            PHP,
        'app/Http/Controllers/DynamicDisableController.php' => <<<'PHP'
            <?php
            $container = app();
            $resolvedActivator = $container->make('modules.activator');
            $operation = 'disable';
            $activatorAlias = $resolvedActivator;
            $activatorAlias->$operation($module);
            PHP,
        'app/Http/Controllers/ReadOnlyActivatorController.php' => <<<'PHP'
            <?php
            $activator = app('modules.activator');
            $status = $activator->status($module);
            PHP,
        'app/Http/Controllers/DynamicReadOnlyActivatorController.php' => <<<'PHP'
            <?php
            $resolvedActivator = resolve('modules.activator');
            $activatorAlias = $resolvedActivator;
            $operation = 'status';
            $status = $activatorAlias->{$operation}($module);
            PHP,
        'Modules/Alpha/Application/DeploymentWriter.php' => '<?php File::put(base_path("modules_"."statuses.json"), "{}");',
        'app/Http/Controllers/RegistryTypedController.php' => <<<'PHP'
            <?php
            use App\ModulePlatform\Services\ModuleRegistry;
            class RegistryTypedController {
                public function toggle(ModuleRegistry $registry, string $module): void {
                    $registry->enable($module);
                    $registry->disable($module);
                }
            }
            PHP,
    ]);

    try {
        $validator = new ModuleArchitectureValidator($root);
        expect($validator->unguardedRouteViolations())->toBe([
            'Modules/Alpha/routes/web.php [GET,HEAD,POST,PUT,PATCH,DELETE,OPTIONS] unguarded -> unguarded',
            'Modules/Users/routes/web.php [GET,HEAD] unguarded -> login/admin',
        ])->and($validator->httpFileActivatorWriteViolations())->toHaveCount(6)
            ->and($validator->httpFileActivatorWriteViolations())->not->toContain(
                'app/Http/Controllers/ReadOnlyActivatorController.php -> non-console deployment state mutation',
                'app/Http/Controllers/DynamicReadOnlyActivatorController.php -> non-console deployment state mutation',
                'app/Http/Controllers/RegistryTypedController.php -> non-console deployment state mutation',
            );
    } finally {
        removeArchitectureFixture($root);
    }
});
