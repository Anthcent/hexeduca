<?php

namespace Modules\Grades\Domain\Entities;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies
 * — same pattern as Modules\Users\Domain\Entities\User.
 */
final class Grade
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $academicPeriodId,
        private readonly int $academicOfferId,
        private readonly int $studentId,
        private readonly int $teacherId,
        private float $value,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function academicPeriodId(): int
    {
        return $this->academicPeriodId;
    }

    public function academicOfferId(): int
    {
        return $this->academicOfferId;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function teacherId(): int
    {
        return $this->teacherId;
    }

    public function value(): float
    {
        return $this->value;
    }

    public function reviseTo(float $value): void
    {
        $this->value = $value;
    }
}
