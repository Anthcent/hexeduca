<?php

namespace Modules\Grades\Application\DTOs;

final readonly class RecordGradeData
{
    public function __construct(
        public int $academicOfferId,
        public int $studentId,
        public int $teacherId,
        public float $value,
        public int $actorId,
        public int $actorSchoolId,
        public int $activeAcademicPeriodId,
        public bool $actorCanDelegate = false,
    ) {}
}
