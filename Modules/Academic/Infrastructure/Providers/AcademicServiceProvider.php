<?php

namespace Modules\Academic\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Academic\Domain\Repositories\GradoRepositoryInterface;
use Modules\Academic\Domain\Repositories\MatriculaRepositoryInterface;
use Modules\Academic\Domain\Repositories\MomentoAcademicoRepositoryInterface;
use Modules\Academic\Domain\Repositories\NivelAcademicoRepositoryInterface;
use Modules\Academic\Domain\Repositories\OfertaAcademicaRepositoryInterface;
use Modules\Academic\Domain\Repositories\PeriodoAcademicoRepositoryInterface;
use Modules\Academic\Domain\Repositories\SeccionRepositoryInterface;
use Modules\Academic\Infrastructure\Period\PeriodoContext;
use Modules\Academic\Infrastructure\Persistence\EloquentGradoRepository;
use Modules\Academic\Infrastructure\Persistence\EloquentMatriculaRepository;
use Modules\Academic\Infrastructure\Persistence\EloquentMomentoAcademicoRepository;
use Modules\Academic\Infrastructure\Persistence\EloquentNivelAcademicoRepository;
use Modules\Academic\Infrastructure\Persistence\EloquentOfertaAcademicaRepository;
use Modules\Academic\Infrastructure\Persistence\EloquentPeriodoAcademicoRepository;
use Modules\Academic\Infrastructure\Persistence\EloquentSeccionRepository;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AcademicServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Academic';

    protected string $nameLower = 'academic';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->name, 'Infrastructure/Database/Migrations'));
        $this->registerInertiaPages();
    }

    /**
     * Register this module's `Resources/js/Pages` directory with Inertia's
     * view finder under the `Academic::` namespace so `Inertia::render`
     * (and `assertInertia(...)->component(...)`) can resolve module-owned
     * Vue pages the same way `resources/js/app.js`'s glob does at runtime.
     */
    protected function registerInertiaPages(): void
    {
        $this->app->afterResolving('inertia.view-finder', function ($finder): void {
            $finder->addNamespace($this->name, module_path($this->name, 'Resources/js/Pages'));
        });
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->bind(NivelAcademicoRepositoryInterface::class, EloquentNivelAcademicoRepository::class);
        $this->app->bind(GradoRepositoryInterface::class, EloquentGradoRepository::class);
        $this->app->bind(SeccionRepositoryInterface::class, EloquentSeccionRepository::class);
        $this->app->bind(PeriodoAcademicoRepositoryInterface::class, EloquentPeriodoAcademicoRepository::class);
        $this->app->bind(MomentoAcademicoRepositoryInterface::class, EloquentMomentoAcademicoRepository::class);
        $this->app->bind(OfertaAcademicaRepositoryInterface::class, EloquentOfertaAcademicaRepository::class);
        $this->app->bind(MatriculaRepositoryInterface::class, EloquentMatriculaRepository::class);

        $this->app->scoped(PeriodoContext::class);

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Register translations.
     */
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

    /**
     * Register config.
     */
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

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
