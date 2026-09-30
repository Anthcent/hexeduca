<?php

namespace Modules\TeachingAssignments\Domain\Entities;

use Modules\TeachingAssignments\Domain\Exceptions\AssignmentEnded;
use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;

/**
 * Who teaches a subject of an offer, and in which role. An assignment is
 * never deleted: replacing or removing it ends it, so the grades module can
 * always tell who was responsible at any date.
 */
final class TeachingAssignment
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $periodId,
        private readonly int $offerId,
        private readonly int $subjectId,
        private readonly int $teacherId,
        private readonly TeachingRole $role,
        private readonly string $startedOn,
        private readonly ?string $endedOn = null,
    ) {}

    public static function start(int $schoolId, int $periodId, int $offerId, int $subjectId, int $teacherId, TeachingRole $role, string $today): self
    {
        return new self(null, $schoolId, $periodId, $offerId, $subjectId, $teacherId, $role, $today);
    }

    /**
     * @throws AssignmentEnded
     */
    public function end(string $today): self
    {
        if ($this->endedOn !== null) {
            throw AssignmentEnded::withId((int) $this->id);
        }

        return new self($this->id, $this->schoolId, $this->periodId, $this->offerId, $this->subjectId, $this->teacherId, $this->role, $this->startedOn, $today);
    }

    public function isActive(): bool
    {
        return $this->endedOn === null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function periodId(): int
    {
        return $this->periodId;
    }

    public function offerId(): int
    {
        return $this->offerId;
    }

    public function subjectId(): int
    {
        return $this->subjectId;
    }

    public function teacherId(): int
    {
        return $this->teacherId;
    }

    public function role(): TeachingRole
    {
        return $this->role;
    }

    public function startedOn(): string
    {
        return $this->startedOn;
    }

    public function endedOn(): ?string
    {
        return $this->endedOn;
    }
}
