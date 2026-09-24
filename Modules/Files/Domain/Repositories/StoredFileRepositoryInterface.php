<?php

namespace Modules\Files\Domain\Repositories;

use Modules\Files\Domain\Entities\StoredFile;

interface StoredFileRepositoryInterface
{
    public function save(StoredFile $file): StoredFile;

    public function findInSchool(int $id, int $schoolId): ?StoredFile;

    public function delete(int $id, int $schoolId): void;
}
