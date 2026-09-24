<?php

namespace Modules\Sections\Domain\Repositories;

use Modules\Sections\Domain\Entities\Section;

interface SectionRepositoryInterface
{
    public function findById(int $id): ?Section;

    public function save(Section $section): Section;
}
