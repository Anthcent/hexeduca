<?php

namespace Modules\Academic\Domain\Entities;

use DateTimeImmutable;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * Enrolls a student into exactly one OfertaAcademica. MUST NOT reference a
 * catalog Grado/Seccion directly — enrollment always targets an offering.
 * Tenant+period tier.
 */
final class Matricula
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $periodoAcademicoId,
        private readonly int $ofertaAcademicaId,
        private readonly int $studentId,
        private string $status,
        private readonly DateTimeImmutable $enrolledAt,
        private readonly int $version = 1,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function periodoAcademicoId(): int
    {
        return $this->periodoAcademicoId;
    }

    public function ofertaAcademicaId(): int
    {
        return $this->ofertaAcademicaId;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function enrolledAt(): DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function changeStatusTo(string $status): void
    {
        $this->status = $status;
    }
}
