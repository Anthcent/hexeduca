<?php

namespace Modules\Sections\Public\Contracts;

use Modules\Sections\Public\DTOs\SectionDTO;

interface SectionReader
{
    public function find(int $id): ?SectionDTO;

    public function findForSchool(int $id, int $schoolId): ?SectionDTO;

    /**
     * @return array<int, SectionDTO>
     */
    public function allForSchool(int $schoolId): array;
}
