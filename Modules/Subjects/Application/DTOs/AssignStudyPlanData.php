<?php

namespace Modules\Subjects\Application\DTOs;

use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

final readonly class AssignStudyPlanData
{
    /**
     * @param  int|null  $gradeLevelId  required for the grade-level scope
     * @param  int|null  $offerId  required for the offer scope
     */
    public function __construct(
        public int $schoolId,
        public int $periodId,
        public int $planId,
        public AssignmentScope $scope,
        public ?int $gradeLevelId = null,
        public ?int $offerId = null,
    ) {}
}
