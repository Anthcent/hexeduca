<?php

namespace Modules\Files\Infrastructure\Persistence;

use Modules\Files\Domain\Entities\StoredFile;
use Modules\Files\Domain\Repositories\StoredFileRepositoryInterface;
use Modules\Files\Infrastructure\Models\SchoolFile;

final class EloquentStoredFileRepository implements StoredFileRepositoryInterface
{
    public function save(StoredFile $file): StoredFile
    {
        $model = $file->id()
            ? SchoolFile::query()->where('school_id', $file->schoolId())->findOrFail($file->id())
            : new SchoolFile;

        $model->fill([
            'school_id' => $file->schoolId(),
            'original_name' => $file->originalName(),
            'mime_type' => $file->mimeType(),
            'size_bytes' => $file->sizeBytes(),
            'storage_path' => $file->storagePath(),
            'uploaded_by' => $file->uploadedBy(),
            'uploader_name' => $file->uploaderName(),
        ])->save();

        return $model->toEntity();
    }

    public function findInSchool(int $id, int $schoolId): ?StoredFile
    {
        return SchoolFile::query()->where('school_id', $schoolId)->find($id)?->toEntity();
    }

    public function delete(int $id, int $schoolId): void
    {
        SchoolFile::query()->where('school_id', $schoolId)->whereKey($id)->delete();
    }
}
