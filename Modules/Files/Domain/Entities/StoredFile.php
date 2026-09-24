<?php

namespace Modules\Files\Domain\Entities;

/**
 * A file in a school's private repository. The uploader may be null once
 * their account is removed; the name snapshot still says who uploaded it.
 */
final class StoredFile
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly string $originalName,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
        private readonly string $storagePath,
        private readonly ?int $uploadedBy,
        private readonly string $uploaderName,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function storagePath(): string
    {
        return $this->storagePath;
    }

    public function uploadedBy(): ?int
    {
        return $this->uploadedBy;
    }

    public function uploaderName(): string
    {
        return $this->uploaderName;
    }

    /**
     * Staff may delete any file of their school; anyone else only the files
     * they uploaded.
     */
    public function canBeDeletedBy(int $userId, bool $mayDeleteAny): bool
    {
        return $mayDeleteAny || ($this->uploadedBy !== null && $this->uploadedBy === $userId);
    }
}
