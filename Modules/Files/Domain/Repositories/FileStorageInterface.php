<?php

namespace Modules\Files\Domain\Repositories;

/**
 * The physical file contents, kept apart from the database row.
 */
interface FileStorageInterface
{
    /**
     * Copies the local file at `$sourcePath` to `$targetPath` on the disk.
     * Throws when it cannot be written.
     */
    public function put(string $sourcePath, string $targetPath): void;

    /**
     * Returns false when the file exists but could not be removed. A file
     * that is already gone counts as deleted.
     */
    public function delete(string $path): bool;
}
