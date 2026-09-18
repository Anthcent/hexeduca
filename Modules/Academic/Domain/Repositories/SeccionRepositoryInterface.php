<?php

namespace Modules\Academic\Domain\Repositories;

use Modules\Academic\Domain\Entities\Seccion;

interface SeccionRepositoryInterface
{
    public function findById(int $id): ?Seccion;

    public function save(Seccion $seccion): Seccion;
}
