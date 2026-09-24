<?php

namespace Modules\AcademicPeriods\Infrastructure\Providers;

use App\AcademicPeriod\Contracts\ActivePeriodResolver;
use Illuminate\Support\ServiceProvider;
use Modules\AcademicPeriods\Domain\Repositories\AcademicPeriodRepositoryInterface;
use Modules\AcademicPeriods\Infrastructure\Http\Resolvers\EloquentActivePeriodResolver;
use Modules\AcademicPeriods\Infrastructure\Persistence\EloquentAcademicPeriodRepository;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AcademicPeriodsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'AcademicPeriods';

    protected string $nameLower = 'academicperiods';

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
     * view finder under the `AcademicPeriods::` namespace.
     */
    protected function registerInertiaPages(): void
    {
        $this->app->afterResolving('inertia.view-finder', function ($finder): void {
            $finder->addNamespace($this->name, module_path($this->name, 'Resources/js/Pages'));
        });
    }

    public function register(): void
    {
        $this->app->bind(AcademicPeriodRepositoryInterface::class, EloquentAcademicPeriodRepository::class);

        // Definitive binding: this module now owns the active-period
        // resolution logic. Replaces the Fase 1 provisional bind that lived
        // in Modules\Academic\Infrastructure\Providers\AcademicServiceProvider.
        $this->app->bind(ActivePeriodResolver::class, EloquentActivePeriodResolver::class);

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
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
