<?php

namespace Modules\Academic\Domain\Repositories;

use Modules\Academic\Domain\Entities\NivelAcademico;

interface NivelAcademicoRepositoryInterface
{
    public function findById(int $id): ?NivelAcademico;

    public function save(NivelAcademico $nivelAcademico): NivelAcademico;
}
