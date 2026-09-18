<?php

namespace Modules\Academic\Domain\Repositories;

use Modules\Academic\Domain\Entities\Grado;

interface GradoRepositoryInterface
{
    public function findById(int $id): ?Grado;

    public function save(Grado $grado): Grado;
}
