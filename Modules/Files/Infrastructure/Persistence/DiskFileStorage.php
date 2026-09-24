<?php

namespace Modules\Files\Infrastructure\Persistence;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Modules\Files\Domain\Repositories\FileStorageInterface;
use RuntimeException;

/**
 * Stores files on the application's default disk. On the stock `local`
 * disk that is `storage/app/private`, which is never publicly served.
 */
final class DiskFileStorage implements FileStorageInterface
{
    public function put(string $sourcePath, string $targetPath): void
    {
        $stored = Storage::disk()->putFileAs(dirname($targetPath), new File($sourcePath), basename($targetPath));

        if ($stored === false) {
            throw new RuntimeException("Could not store the file at [{$targetPath}].");
        }
    }

    public function delete(string $path): bool
    {
        return Storage::disk()->delete($path);
    }
}
