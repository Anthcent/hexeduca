<?php

namespace Modules\Sections\Public\DTOs;

final readonly class SectionDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $name,
    ) {}
}
