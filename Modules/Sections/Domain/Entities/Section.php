<?php

namespace Modules\Sections\Domain\Entities;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * Fixed catalog label (A/B/...). No foreign key to any other module.
 *
 * Port of Modules\Academic\Domain\Entities\Seccion — same behavior, this
 * module is now the sole owner of the section domain.
 */
final class Section
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
