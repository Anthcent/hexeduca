<?php

namespace Modules\Files\Domain\Exceptions;

use DomainException;

final class FileDeletionDenied extends DomainException
{
    public static function notOwner(int $fileId): self
    {
        return new self("Only the uploader may delete file [{$fileId}].");
    }
}
