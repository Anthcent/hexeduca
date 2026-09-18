<?php

namespace Modules\Academic\Domain\Entities;

use Modules\Academic\Domain\ValueObjects\DateRange;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * A grading-cut subdivision of exactly one PeriodoAcademico, global within
 * its period — global-within-period tier: no school_id, tenant isolation is
 * inherited transitively via periodoAcademicoId.
 */
final class MomentoAcademico
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $periodoAcademicoId,
        private string $name,
        private int $order,
        private DateRange $dateRange,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function periodoAcademicoId(): int
    {
        return $this->periodoAcademicoId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function dateRange(): DateRange
    {
        return $this->dateRange;
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
