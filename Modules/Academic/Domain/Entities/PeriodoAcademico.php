<?php

namespace Modules\Academic\Domain\Entities;

use Modules\Academic\Domain\ValueObjects\DateRange;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * The top-level temporal scope (e.g. "2025-2026") that all instance-level
 * academic entities anchor to. Tenant-only tier: PeriodoAcademico cannot be
 * period-scoped against itself.
 */
final class PeriodoAcademico
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private string $name,
        private DateRange $dateRange,
        private bool $isActive,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function dateRange(): DateRange
    {
        return $this->dateRange;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function renameTo(string $name): void
    {
        $this->name = $name;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }
}
