<?php

namespace Modules\AcademicMoments\Domain\Entities;

use Modules\AcademicMoments\Domain\ValueObjects\DateRange;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * A grading-cut subdivision of exactly one AcademicPeriod, global within
 * its period — global-within-period tier: no school_id, tenant isolation is
 * inherited transitively via academicPeriodId (the Tenant -> Period chain
 * is the one vertical dependency the plan sanctions, see plan §1).
 *
 * Port of Modules\Academic\Domain\Entities\MomentoAcademico — same
 * behavior, this module is now the sole owner of the academic-moment
 * domain.
 */
final class AcademicMoment
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $academicPeriodId,
        private string $name,
        private int $order,
        private DateRange $dateRange,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function academicPeriodId(): int
    {
        return $this->academicPeriodId;
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
