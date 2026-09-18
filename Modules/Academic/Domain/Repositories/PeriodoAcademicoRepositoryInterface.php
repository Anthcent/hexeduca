<?php

namespace Modules\Academic\Domain\Repositories;

use Modules\Academic\Domain\Entities\PeriodoAcademico;

interface PeriodoAcademicoRepositoryInterface
{
    public function findById(int $id): ?PeriodoAcademico;

    public function save(PeriodoAcademico $periodoAcademico): PeriodoAcademico;
}
