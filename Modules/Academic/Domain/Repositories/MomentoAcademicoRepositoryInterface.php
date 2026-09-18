<?php

namespace Modules\Academic\Domain\Repositories;

use Modules\Academic\Domain\Entities\MomentoAcademico;

interface MomentoAcademicoRepositoryInterface
{
    public function findById(int $id): ?MomentoAcademico;

    public function save(MomentoAcademico $momentoAcademico): MomentoAcademico;
}
