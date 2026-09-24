<?php

namespace Modules\AcademicLevels\Public\DTOs;

/**
 * Plain read-only projection of an AcademicLevel. No Eloquent, no
 * behavior — consumers outside this module may only ever see this shape,
 * never the owning module's Eloquent model.
 */
final readonly class AcademicLevelDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $name,
    ) {}
}
