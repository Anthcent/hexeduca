<?php

namespace Modules\Academic\Domain\Entities;

use Modules\Academic\Domain\ValueObjects\Capacity;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * Instantiates one catalog Grado + Seccion within exactly one
 * PeriodoAcademico. Tenant+period tier.
 */
final class OfertaAcademica
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $periodoAcademicoId,
        private readonly int $gradoId,
        private readonly int $seccionId,
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

    public function periodoAcademicoId(): int
    {
        return $this->periodoAcademicoId;
    }

    public function gradoId(): int
    {
        return $this->gradoId;
    }

    public function seccionId(): int
    {
        return $this->seccionId;
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
