<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * Invalid subject data. `field` names the offending input.
 */
final class InvalidSubject extends DomainException
{
    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function weeklyHoursNotPositive(): self
    {
        return new self('Weekly hours must be a positive integer.', 'weekly_hours');
    }

    public static function unknownGradeLevel(int $gradeLevelId): self
    {
        return new self("Grade level [{$gradeLevelId}] does not belong to the school.", 'grade_level_id');
    }

    public static function blankName(): self
    {
        return new self('The subject name is required.', 'name');
    }
}
