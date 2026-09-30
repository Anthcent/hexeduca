<?php

namespace Modules\TeachingAssignments\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\TeachingAssignments\Domain\Repositories\TeachingAssignmentRepositoryInterface;
use Modules\TeachingAssignments\Infrastructure\Persistence\EloquentTeachingAssignmentReader;
use Modules\TeachingAssignments\Infrastructure\Persistence\EloquentTeachingAssignmentRepository;
use Modules\TeachingAssignments\Public\Contracts\TeachingAssignmentReader;

class TeachingAssignmentsServiceProvider extends ServiceProvider
{
    protected string $name = 'TeachingAssignments';

    protected string $nameLower = 'teachingassignments';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'Infrastructure/Database/Migrations'));
        $this->registerInertiaPages();
    }

    public function register(): void
    {
        $this->app->bind(TeachingAssignmentRepositoryInterface::class, EloquentTeachingAssignmentRepository::class);
        $this->app->bind(TeachingAssignmentReader::class, EloquentTeachingAssignmentReader::class);

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
