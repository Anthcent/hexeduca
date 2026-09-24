<?php

namespace Modules\Files\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Files\Domain\Exceptions\FileDeletionDenied;
use Modules\Files\Domain\Exceptions\FileNotFound;
use Modules\Files\Domain\Repositories\FileStorageInterface;
use Modules\Files\Domain\Repositories\StoredFileRepositoryInterface;
use RuntimeException;

final class DeleteFile
{
    public function __construct(
        private readonly StoredFileRepositoryInterface $files,
        private readonly FileStorageInterface $storage,
    ) {}

    /**
     * Deletes the row and then the physical file inside one transaction: if
     * the physical file cannot be removed, the row deletion rolls back and
     * both stay, so the list never shows a file that cannot be downloaded
     * and no row disappears while its file stays on the disk.
     *
     * @throws FileNotFound when the file is not in this school
     * @throws FileDeletionDenied when the user may not delete it
     */
    public function handle(int $fileId, int $schoolId, int $userId, bool $mayDeleteAny): void
    {
        $file = $this->files->findInSchool($fileId, $schoolId) ?? throw FileNotFound::withId($fileId);

        if (! $file->canBeDeletedBy($userId, $mayDeleteAny)) {
            throw FileDeletionDenied::notOwner($fileId);
        }

        DB::transaction(function () use ($file, $schoolId): void {
            $this->files->delete($file->id(), $schoolId);

            if (! $this->storage->delete($file->storagePath())) {
                throw new RuntimeException("Could not delete the stored file of file [{$file->id()}].");
            }
        });
    }
}
