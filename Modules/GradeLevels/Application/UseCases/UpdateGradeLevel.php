<?php

namespace Modules\GradeLevels\Application\UseCases;

use Modules\GradeLevels\Domain\Entities\GradeLevel;
use Modules\GradeLevels\Domain\Repositories\GradeLevelRepositoryInterface;
use Modules\GradeLevels\Public\Events\GradeLevelUpdated;

final class UpdateGradeLevel
{
    public function __construct(
        private readonly GradeLevelRepositoryInterface $repository,
    ) {}

    public function handle(GradeLevel $gradeLevel, string $name, int $order): GradeLevel
    {
        $gradeLevel->renameTo($name);
        $gradeLevel->reorderTo($order);

        $gradeLevel = $this->repository->save($gradeLevel);

        event(new GradeLevelUpdated(
            gradeLevelId: $gradeLevel->id(),
            schoolId: $gradeLevel->schoolId(),
            name: $gradeLevel->name(),
        ));

        return $gradeLevel;
    }
}
