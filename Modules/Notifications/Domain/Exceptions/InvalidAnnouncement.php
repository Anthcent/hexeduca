<?php

namespace Modules\Notifications\Domain\Exceptions;

use DomainException;

final class InvalidAnnouncement extends DomainException
{
    public static function titleRequired(): self
    {
        return new self('The announcement title is required.');
    }

    public static function titleTooLong(int $max): self
    {
        return new self("The announcement title may not be longer than {$max} characters.");
    }

    public static function bodyRequired(): self
    {
        return new self('The announcement body is required.');
    }

    public static function bodyTooLong(int $max): self
    {
        return new self("The announcement body may not be longer than {$max} characters.");
    }

    public static function unknownAudience(string $audience): self
    {
        return new self("Unknown announcement audience [{$audience}].");
    }
}
