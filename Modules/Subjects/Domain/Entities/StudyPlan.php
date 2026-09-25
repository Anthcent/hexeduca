<?php

namespace Modules\Subjects\Domain\Entities;

use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\ObservationRequired;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\ValueObjects\RecordStatus;

/**
 * A school's study plan, identified by its official code. Codes may repeat
 * inside a school; when they do, the observation tells the plans apart.
 */
final class StudyPlan
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly string $code,
        private readonly string $name,
        private readonly ?string $observation,
        private readonly RecordStatus $status = RecordStatus::Active,
    ) {}

    /**
     * @param  bool  $codeTaken  whether another plan of the school (active or archived) already uses the code
     *
     * @throws ObservationRequired
     */
    public static function create(int $schoolId, string $code, string $name, ?string $observation, bool $codeTaken): self
    {
        $observation = self::normalizeObservation($observation);
        self::assertObservationForDuplicateCode(trim($code), $observation, $codeTaken);

        return new self(null, $schoolId, trim($code), trim($name), $observation);
    }

    /**
     * @throws RecordArchived
     * @throws ObservationRequired
     */
    public function withDetails(string $code, string $name, ?string $observation, bool $codeTaken): self
    {
        $this->assertEditable();
        $observation = self::normalizeObservation($observation);
        self::assertObservationForDuplicateCode(trim($code), $observation, $codeTaken);

        return new self($this->id, $this->schoolId, trim($code), trim($name), $observation, $this->status);
    }

    public function archive(): self
    {
        if ($this->isArchived()) {
            throw InvalidStatusChange::alreadyArchived();
        }

        return new self($this->id, $this->schoolId, $this->code, $this->name, $this->observation, RecordStatus::Archived);
    }

    public function reactivate(): self
    {
        if (! $this->isArchived()) {
            throw InvalidStatusChange::notArchived();
        }

        return new self($this->id, $this->schoolId, $this->code, $this->name, $this->observation, RecordStatus::Active);
    }

    /**
     * @throws RecordArchived
     */
    public function assertEditable(): void
    {
        if ($this->isArchived()) {
            throw RecordArchived::plan($this->id);
        }
    }

    /**
     * Only a plan with no related data may be deleted; otherwise it is archived.
     */
    public static function canBeDeleted(int $subjectCount, int $assignmentCount): bool
    {
        return $subjectCount === 0 && $assignmentCount === 0;
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

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function observation(): ?string
    {
        return $this->observation;
    }

    public function status(): RecordStatus
    {
        return $this->status;
    }

    private static function normalizeObservation(?string $observation): ?string
    {
        $observation = $observation === null ? null : trim($observation);

        return $observation === '' ? null : $observation;
    }

    private static function assertObservationForDuplicateCode(string $code, ?string $observation, bool $codeTaken): void
    {
        if ($codeTaken && $observation === null) {
            throw ObservationRequired::forDuplicateCode($code);
        }
    }
}
