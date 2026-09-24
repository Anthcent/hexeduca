<?php

namespace Modules\Files\Application\UseCases;

use Modules\Files\Application\DTOs\UploadFileData;
use Modules\Files\Domain\Entities\StoredFile;
use Modules\Files\Domain\Repositories\FileStorageInterface;
use Modules\Files\Domain\Repositories\StoredFileRepositoryInterface;
use Modules\Files\Domain\ValueObjects\OriginalFileName;
use Modules\Files\Domain\ValueObjects\StoragePath;
use Modules\Files\Domain\ValueObjects\UploadRules;
use Throwable;

final class UploadFile
{
    public function __construct(
        private readonly StoredFileRepositoryInterface $files,
        private readonly FileStorageInterface $storage,
    ) {}

    /**
     * Writes the physical file first, then its row. If the row cannot be
     * saved, the physical file is removed so no orphan stays on the disk.
     */
    public function handle(UploadFileData $data): StoredFile
    {
        UploadRules::assertAcceptable($data->detectedExtension, $data->sizeBytes);
        $originalName = new OriginalFileName($data->originalName);
        $path = StoragePath::generate($data->schoolId, $data->detectedExtension);

        $this->storage->put($data->sourcePath, $path);

        try {
            return $this->files->save(new StoredFile(
                id: null,
                schoolId: $data->schoolId,
                originalName: $originalName->value(),
                mimeType: $data->mimeType,
                sizeBytes: $data->sizeBytes,
                storagePath: $path,
                uploadedBy: $data->uploaderId,
                uploaderName: $data->uploaderName,
            ));
        } catch (Throwable $e) {
            $this->storage->delete($path);

            throw $e;
        }
    }
}
