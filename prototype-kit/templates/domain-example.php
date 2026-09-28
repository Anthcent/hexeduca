<?php

declare(strict_types=1);

// Reference shape for domain/ code. The names are an example; replace them with your module's.
// Plain PHP 8.3: no framework, no database, no clock. The caller passes "today" in.

final class DuplicateSheet extends DomainException {}

final class SheetLocked extends DomainException {}

final class InvalidAttendanceStatus extends DomainException {}

// Value object: immutable and validated on creation.
final class AttendanceStatus
{
    private const ALLOWED = ['present', 'absent', 'late'];

    private function __construct(public readonly string $value) {}

    public static function from(string $value): self
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new InvalidAttendanceStatus($value);
        }

        return new self($value);
    }
}

// Entity: its methods enforce the rules.
final class AttendanceSheet
{
    /** @var array<int, AttendanceStatus> keyed by student id */
    private array $marks = [];

    private bool $closed = false;

    public function __construct(
        public readonly int $academicOfferId,
        public readonly string $date, // Y-m-d
    ) {}

    // R2: a closed sheet can't be changed.
    public function mark(int $studentId, AttendanceStatus $status): void
    {
        if ($this->closed) {
            throw new SheetLocked((string) $this->academicOfferId);
        }

        $this->marks[$studentId] = $status;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

// Service: a rule that needs data outside one entity.
final class SheetUniqueness
{
    // R3: one sheet per offer and date.
    /** @param list<string> $existingDates dates that already have a sheet for this offer */
    public function ensureNew(string $date, array $existingDates): void
    {
        if (in_array($date, $existingDates, true)) {
            throw new DuplicateSheet($date);
        }
    }
}
