<?php

namespace Modules\Academic\Domain\Entities;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * Fixed catalog data: no foreign key to any academic cycle or period.
 * Defined once per tenant and reused across every PeriodoAcademico.
 */
final class NivelAcademico
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private string $name,
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

    public function renameTo(string $name): void
    {
        $this->name = $name;
    }
}
