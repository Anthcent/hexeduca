<?php

namespace Modules\TeachingAssignments\Public\DTOs;

final readonly class TeachingAssignmentDTO
{
    public function __construct(
        public int $id,
        public int $periodId,
        public int $offerId,
        public int $subjectId,
        public int $teacherId,
        // 'titular' or 'suplente'
        public string $role,
        public string $startedOn,
        public ?string $endedOn,
    ) {}
}
