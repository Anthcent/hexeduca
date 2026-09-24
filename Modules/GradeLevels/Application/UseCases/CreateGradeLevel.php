<?php

namespace Modules\GradeLevels\Application\UseCases;

use Modules\GradeLevels\Application\DTOs\CreateGradeLevelData;
use Modules\GradeLevels\Domain\Entities\GradeLevel;
use Modules\GradeLevels\Domain\Repositories\GradeLevelRepositoryInterface;
use Modules\GradeLevels\Public\Events\GradeLevelCreated;

final class CreateGradeLevel
{
    public function __construct(
        private readonly GradeLevelRepositoryInterface $repository,
    ) {}

    public function handle(CreateGradeLevelData $data): GradeLevel
    {
        $gradeLevel = new GradeLevel(
            id: null,
            schoolId: $data->schoolId,
            academicLevelId: $data->academicLevelId,
            name: $data->name,
            order: $data->order,
        );

        $gradeLevel = $this->repository->save($gradeLevel);

        event(new GradeLevelCreated(
            gradeLevelId: $gradeLevel->id(),
            schoolId: $gradeLevel->schoolId(),
            name: $gradeLevel->name(),
        ));

        return $gradeLevel;
    }
}
