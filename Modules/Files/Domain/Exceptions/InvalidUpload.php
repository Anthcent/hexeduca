<?php

namespace Modules\Files\Domain\Exceptions;

use DomainException;

final class InvalidUpload extends DomainException
{
    public static function typeNotAllowed(string $extension): self
    {
        return new self("Files of type [{$extension}] are not allowed.");
    }

    public static function empty(): self
    {
        return new self('The file is empty.');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self("The file may not be larger than {$maxBytes} bytes.");
    }

    public static function nameRequired(): self
    {
        return new self('The file name is required.');
    }
}
