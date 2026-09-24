<?php

namespace Modules\Files\Domain\ValueObjects;

use Modules\Files\Domain\Exceptions\InvalidUpload;

/**
 * Where a file lives on the disk: `schools/{school_id}/files/{random}.{ext}`.
 * Every part is generated or detected by the server; nothing comes from the
 * client filename.
 */
final class StoragePath
{
    public static function generate(int $schoolId, string $detectedExtension): string
    {
        $extension = strtolower($detectedExtension);

        if (! UploadRules::allowsExtension($extension)) {
            throw InvalidUpload::typeNotAllowed($detectedExtension);
        }

        return sprintf('schools/%d/files/%s.%s', $schoolId, bin2hex(random_bytes(20)), $extension);
    }
}
