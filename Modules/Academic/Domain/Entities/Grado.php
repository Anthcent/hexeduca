<?php

namespace Modules\Academic\Domain\Entities;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * Fixed catalog data belonging to a NivelAcademico. No foreign key to any
 * academic cycle or period; defined once per tenant and reused across every
 * PeriodoAcademico.
 */
final class Grado
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $nivelAcademicoId,
        private string $name,
        private int $order,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function nivelAcademicoId(): int
    {
        return $this->nivelAcademicoId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function order(): int
    {
        return $this->order;
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
