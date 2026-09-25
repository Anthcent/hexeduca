<?php

namespace Modules\Subjects\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;
use Modules\Subjects\Infrastructure\Persistence\EloquentPlanAssignmentRepository;
use Modules\Subjects\Infrastructure\Persistence\EloquentStudyPlanRepository;
use Modules\Subjects\Infrastructure\Persistence\EloquentSubjectRepository;

class SubjectsServiceProvider extends ServiceProvider
{
    protected string $name = 'Subjects';

    protected string $nameLower = 'subjects';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'Infrastructure/Database/Migrations'));
        $this->registerInertiaPages();
    }

    public function register(): void
    {
        $this->app->bind(StudyPlanRepositoryInterface::class, EloquentStudyPlanRepository::class);
        $this->app->bind(SubjectRepositoryInterface::class, EloquentSubjectRepository::class);
        $this->app->bind(PlanAssignmentRepositoryInterface::class, EloquentPlanAssignmentRepository::class);

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
