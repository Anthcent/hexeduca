<?php

namespace Modules\Subjects\Domain\Entities;

use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\InvalidSubject;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\ValueObjects\RecordStatus;

/**
 * A subject of a study plan for one grade level (the plan's "year").
 * Duplicates are allowed: the same name may repeat in a plan and year.
 */
final class Subject
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $planId,
        private readonly int $gradeLevelId,
        private readonly string $name,
        private readonly ?string $code,
        private readonly ?int $weeklyHours,
        private readonly RecordStatus $status = RecordStatus::Active,
    ) {
        if (trim($name) === '') {
            throw InvalidSubject::blankName();
        }

        if ($weeklyHours !== null && $weeklyHours < 1) {
            throw InvalidSubject::weeklyHoursNotPositive();
        }
    }

    public static function create(int $schoolId, int $planId, int $gradeLevelId, string $name, ?string $code, ?int $weeklyHours): self
    {
        return new self(null, $schoolId, $planId, $gradeLevelId, trim($name), self::normalizeCode($code), $weeklyHours);
    }

    /**
     * @throws RecordArchived
     */
    public function withDetails(int $gradeLevelId, string $name, ?string $code, ?int $weeklyHours): self
    {
        $this->assertEditable();

        return new self($this->id, $this->schoolId, $this->planId, $gradeLevelId, trim($name), self::normalizeCode($code), $weeklyHours, $this->status);
    }

    public function archive(): self
    {
        if ($this->isArchived()) {
            throw InvalidStatusChange::alreadyArchived();
        }

        return new self($this->id, $this->schoolId, $this->planId, $this->gradeLevelId, $this->name, $this->code, $this->weeklyHours, RecordStatus::Archived);
    }

    public function reactivate(): self
    {
        if (! $this->isArchived()) {
            throw InvalidStatusChange::notArchived();
        }

        return new self($this->id, $this->schoolId, $this->planId, $this->gradeLevelId, $this->name, $this->code, $this->weeklyHours, RecordStatus::Active);
    }

    /**
     * @throws RecordArchived
     */
    public function assertEditable(): void
    {
        if ($this->isArchived()) {
            throw RecordArchived::subject($this->id);
        }
    }

    /**
     * Only a subject that no assignment of its plan activates, and with no
     * exclusions, may be deleted; otherwise it is archived.
     */
    public static function canBeDeleted(bool $activatedByAnAssignment, bool $hasExclusions): bool
    {
        return ! $activatedByAnAssignment && ! $hasExclusions;
    }

    public function isArchived(): bool
    {
        return $this->status === RecordStatus::Archived;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function planId(): int
    {
        return $this->planId;
    }

    public function gradeLevelId(): int
    {
        return $this->gradeLevelId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function weeklyHours(): ?int
    {
        return $this->weeklyHours;
    }

    public function status(): RecordStatus
    {
        return $this->status;
    }

    private static function normalizeCode(?string $code): ?string
    {
        $code = $code === null ? null : trim($code);

        return $code === '' ? null : $code;
    }
}
