<?php

namespace Modules\Grades\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Grades\Domain\Repositories\ConductBookRepositoryInterface;
use Modules\Grades\Domain\Repositories\GradeBookRepositoryInterface;
use Modules\Grades\Infrastructure\Console\Commands\RebuildEnrollmentProjection;
use Modules\Grades\Infrastructure\Persistence\EloquentConductBookRepository;
use Modules\Grades\Infrastructure\Persistence\EloquentGradeBookRepository;

class GradesServiceProvider extends ServiceProvider
{
    protected string $name = 'Grades';

    protected string $nameLower = 'grades';

    public function boot(): void
    {
        $this->commands([RebuildEnrollmentProjection::class]);
        $this->loadMigrationsFrom(module_path($this->name, 'Infrastructure/Database/Migrations'));
        $this->registerInertiaPages();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), $this->nameLower);
        $this->app->bind(GradeBookRepositoryInterface::class, EloquentGradeBookRepository::class);
        $this->app->bind(ConductBookRepositoryInterface::class, EloquentConductBookRepository::class);

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerInertiaPages(): void
    {
        $this->app->afterResolving('inertia.view-finder', function ($finder): void {
            $finder->addNamespace($this->name, module_path($this->name, 'Resources/js/Pages'));
        });
    }
}
