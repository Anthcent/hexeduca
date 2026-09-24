<?php

namespace Modules\AcademicOffers\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AcademicOffers\Domain\Repositories\AcademicOfferRepositoryInterface;
use Modules\AcademicOffers\Infrastructure\Console\Commands\RebuildTeacherProjectionCommand;
use Modules\AcademicOffers\Infrastructure\Persistence\EloquentAcademicOfferProjectionSource;
use Modules\AcademicOffers\Infrastructure\Persistence\EloquentAcademicOfferReader;
use Modules\AcademicOffers\Infrastructure\Persistence\EloquentAcademicOfferRepository;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferProjectionSource;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AcademicOffersServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'AcademicOffers';

    protected string $nameLower = 'academicoffers';

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
        $this->app->bind(AcademicOfferRepositoryInterface::class, EloquentAcademicOfferRepository::class);

        // Public contract binding: the reader other modules (Enrollments,
        // Schedule) are allowed to depend on for building their own
        // projections. See plan §5/§10.
        $this->app->bind(AcademicOfferProjectionSource::class, EloquentAcademicOfferProjectionSource::class);

        // Request-time reader for sibling-module UI pickers (e.g.
        // Enrollments). See plan §5.
        $this->app->bind(AcademicOfferReader::class, EloquentAcademicOfferReader::class);

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([RebuildTeacherProjectionCommand::class]);
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
