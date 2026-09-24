<?php

namespace Modules\Grades\Application\UseCases;

use DomainException;
use Modules\Grades\Domain\Entities\Grade;
use Modules\Grades\Domain\Repositories\GradeRepositoryInterface;

final class UpdateGrade
{
    public function __construct(
        private readonly GradeRepositoryInterface $grades,
    ) {}

    public function handle(int $gradeId, float $value): Grade
    {
        $grade = $this->grades->findById($gradeId);

        if ($grade === null) {
            throw new DomainException('Grade not found.');
        }

        $grade->reviseTo($value);

        return $this->grades->save($grade);
    }
}
