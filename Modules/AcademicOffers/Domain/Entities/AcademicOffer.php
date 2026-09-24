<?php

namespace Modules\AcademicOffers\Domain\Entities;

use Modules\AcademicOffers\Domain\ValueObjects\Capacity;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * Instantiates one GradeLevel + Section pair within exactly one
 * AcademicPeriod. Tenant+period tier.
 *
 * Port of Modules\Academic\Domain\Entities\OfertaAcademica — same
 * behavior, this module is now the sole owner of the academic-offer
 * domain.
 *
 * `teacherId` still refers to a Modules\Users id by raw FK — the Users
 * coupling is deliberately NOT removed in this phase. Plan §13 Fase 6
 * ("Eliminar imports cruzados hacia Users") is the dedicated phase for
 * that; doing it here would collapse two reviewable phases into one.
 */
final class AcademicOffer
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $academicPeriodId,
        private readonly int $gradeLevelId,
        private readonly int $sectionId,
        private ?int $teacherId,
        private Capacity $capacity,
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

    public function gradeLevelId(): int
    {
        return $this->gradeLevelId;
    }

    public function sectionId(): int
    {
        return $this->sectionId;
    }

    public function teacherId(): ?int
    {
        return $this->teacherId;
    }

    public function capacity(): Capacity
    {
        return $this->capacity;
    }

    public function assignTeacher(?int $teacherId): void
    {
        $this->teacherId = $teacherId;
    }
}
