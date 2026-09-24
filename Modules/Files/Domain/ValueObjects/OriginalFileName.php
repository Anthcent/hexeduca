<?php

namespace Modules\Files\Domain\ValueObjects;

use Modules\Files\Domain\Exceptions\InvalidUpload;

/**
 * The name the uploader gave the file, kept only for display and as the
 * download name. It never becomes part of a storage path. Directory parts,
 * control and invisible formatting characters are removed so the name is
 * safe to put in a Content-Disposition header.
 */
final class OriginalFileName
{
    public const MAX_LENGTH = 255;

    private readonly string $value;

    public function __construct(string $name)
    {
        $name = mb_scrub($name, 'UTF-8');
        $name = (string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', $name);
        $name = preg_split('#[/\\\\]#', $name);
        $name = trim((string) end($name));

        if ($name === '') {
            throw InvalidUpload::nameRequired();
        }

        $this->value = self::truncate($name);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Shortens the name to the column length, keeping the extension.
     */
    private static function truncate(string $name): string
    {
        if (mb_strlen($name) <= self::MAX_LENGTH) {
            return $name;
        }

        $dot = mb_strrpos($name, '.');
        $extension = $dot === false ? '' : mb_substr($name, $dot);

        if (mb_strlen($extension) > 16) {
            $extension = '';
        }

        return mb_substr($name, 0, self::MAX_LENGTH - mb_strlen($extension)).$extension;
    }
}
