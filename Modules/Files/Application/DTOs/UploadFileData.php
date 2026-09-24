<?php

namespace Modules\Files\Application\DTOs;

final readonly class UploadFileData
{
    /**
     * @param  string  $sourcePath  Local path of the uploaded temporary file.
     * @param  string  $detectedExtension  Extension detected from the file content.
     * @param  string  $mimeType  MIME type detected from the file content.
     */
    public function __construct(
        public int $schoolId,
        public int $uploaderId,
        public string $uploaderName,
        public string $originalName,
        public string $sourcePath,
        public string $detectedExtension,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}
