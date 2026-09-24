<?php

namespace Modules\Sections\Application\DTOs;

final readonly class CreateSectionData
{
    public function __construct(
        public int $schoolId,
        public string $name,
    ) {}
}
