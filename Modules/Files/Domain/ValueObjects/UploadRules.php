<?php

namespace Modules\Files\Domain\ValueObjects;

use Modules\Files\Domain\Exceptions\InvalidUpload;

/**
 * What the repository accepts: at most 10 MB, and only PDF, JPG, PNG, Word,
 * Excel, and PowerPoint documents. The extension checked here is the one
 * detected from the file content, never the one in the client filename.
 */
final class UploadRules
{
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    public const MAX_SIZE_KILOBYTES = self::MAX_SIZE_BYTES / 1024;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    public static function assertAcceptable(string $detectedExtension, int $sizeBytes): void
    {
        if (! self::allowsExtension($detectedExtension)) {
            throw InvalidUpload::typeNotAllowed($detectedExtension);
        }

        if ($sizeBytes <= 0) {
            throw InvalidUpload::empty();
        }

        if ($sizeBytes > self::MAX_SIZE_BYTES) {
            throw InvalidUpload::tooLarge(self::MAX_SIZE_BYTES);
        }
    }

    public static function allowsExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::ALLOWED_EXTENSIONS, true);
    }
}
