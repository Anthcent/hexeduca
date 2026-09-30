<?php

namespace Modules\TeachingAssignments\Application\DTOs;

use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;

final readonly class AssignTeacherData
{
    public function __construct(
        public int $schoolId,
        public int $periodId,
        public int $offerId,
        public int $subjectId,
        public int $teacherId,
        public TeachingRole $role,
    ) {}
}
