<?php

namespace Modules\Enrollments\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Enrollments\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Enrollments\Infrastructure\Console\Commands\RebuildStudentProjectionCommand;
use Modules\Enrollments\Infrastructure\Persistence\EloquentEnrollmentProjectionSource;
use Modules\Enrollments\Infrastructure\Persistence\EloquentEnrollmentRepository;
use Modules\Enrollments\Public\Contracts\EnrollmentProjectionSource;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class EnrollmentsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Enrollments';

    protected string $nameLower = 'enrollments';

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->name, 'Infrastructure/Database/Migrations'));
        $this->registerInertiaPages();
    }

    protected function registerInertiaPages(): void
    {
        $this->app->afterResolving('inertia.view-finder', function ($finder): void {
            $finder->addNamespace($this->name, module_path($this->name, 'Resources/js/Pages'));
        });
    }

    public function register(): void
    {
        $this->app->bind(EnrollmentRepositoryInterface::class, EloquentEnrollmentRepository::class);

        // Public contract binding: the reader other modules (Grades) are
        // allowed to depend on for building their own projections. See
        // plan §5/§10.
        $this->app->bind(EnrollmentProjectionSource::class, EloquentEnrollmentProjectionSource::class);

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([RebuildStudentProjectionCommand::class]);
    }

    protected function registerCommandSchedules(): void
    {
        //
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    protected function registerConfig(): void
    {
        $relativeConfigPath = config('modules.paths.generator.config.path');
        $configPath = module_path($this->name, $relativeConfigPath);

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $configKey = $this->nameLower.'.'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
                    $key = ($relativePath === 'config.php') ? $this->nameLower : $configKey;

                    $this->publishes([$file->getPathname() => config_path($relativePath)], 'config');
                    $this->mergeConfigFrom($file->getPathname(), $key);
                }
            }
        }
    }

    public function provides(): array
    {
        return [];
    }
}
