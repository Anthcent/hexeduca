<?php

namespace Modules\GradeLevels\Domain\Entities;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * School grade (1er año, 2do año, ...). NOT to be confused with
 * Modules\Grades (student grading/calificaciones) — deliberately distinct
 * names, see plan §1.
 *
 * Port of Modules\Academic\Domain\Entities\Grado — same behavior, this
 * module is now the sole owner of the grade-level domain.
 */
final class GradeLevel
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $academicLevelId,
        private string $name,
        private int $order,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    /**
     * Foreign identifier only — GradeLevels never holds a reference to
     * AcademicLevels' Eloquent model. Resolving the level's own data (e.g.
     * its name) goes through Modules\AcademicLevels\Public\Contracts\AcademicLevelReader.
     */
    public function academicLevelId(): int
    {
        return $this->academicLevelId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function renameTo(string $name): void
    {
        $this->name = $name;
    }

    public function reorderTo(int $order): void
    {
        $this->order = $order;
    }
}
