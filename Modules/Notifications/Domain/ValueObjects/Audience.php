<?php

namespace Modules\Notifications\Domain\ValueObjects;

use Modules\Notifications\Domain\Exceptions\InvalidAnnouncement;

/**
 * Who receives an announcement. Always resolved inside the sender's
 * school; `all` means teachers plus students.
 */
enum Audience: string
{
    case Teachers = 'teachers';
    case Students = 'students';
    case All = 'all';

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidAnnouncement::unknownAudience($value);
    }

    public function includesTeachers(): bool
    {
        return $this === self::Teachers || $this === self::All;
    }

    public function includesStudents(): bool
    {
        return $this === self::Students || $this === self::All;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
