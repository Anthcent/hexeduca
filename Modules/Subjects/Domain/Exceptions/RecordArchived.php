<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * The plan or subject is archived: it is read-only and cannot be edited or
 * newly assigned.
 */
final class RecordArchived extends DomainException
{
    public const PLAN = 'plan';

    public const SUBJECT = 'subject';

    private function __construct(string $message, public readonly string $kind)
    {
        parent::__construct($message);
    }

    public static function plan(?int $id): self
    {
        return new self("Study plan [{$id}] is archived.", self::PLAN);
    }

    public static function subject(?int $id): self
    {
        return new self("Subject [{$id}] is archived.", self::SUBJECT);
    }
}
