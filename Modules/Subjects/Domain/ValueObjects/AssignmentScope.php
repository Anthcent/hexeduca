<?php

namespace Modules\Subjects\Domain\ValueObjects;

/**
 * Where a plan assignment applies inside an academic period. The most
 * specific scope wins when several cover the same offer.
 */
enum AssignmentScope: string
{
    case School = 'school';
    case GradeLevel = 'grade_level';
    case Offer = 'offer';

    public function precedence(): int
    {
        return match ($this) {
            self::School => 1,
            self::GradeLevel => 2,
            self::Offer => 3,
        };
    }
}
