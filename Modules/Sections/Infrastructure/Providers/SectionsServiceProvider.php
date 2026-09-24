<?php

namespace Modules\Sections\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Sections\Domain\Repositories\SectionRepositoryInterface;
use Modules\Sections\Infrastructure\Persistence\EloquentSectionReader;
use Modules\Sections\Infrastructure\Persistence\EloquentSectionRepository;
use Modules\Sections\Public\Contracts\SectionReader;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SectionsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Sections';

    protected string $nameLower = 'sections';

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
        $this->app->bind(SectionRepositoryInterface::class, EloquentSectionRepository::class);

        // Public contract binding: the reader other modules (e.g.
        // AcademicOffers) are allowed to depend on. See plan §5.
        $this->app->bind(SectionReader::class, EloquentSectionReader::class);

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerCommands(): void
    {
        //
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
