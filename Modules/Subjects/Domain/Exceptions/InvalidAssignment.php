<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * The assignment target or its input is not valid for the school/period.
 * `field` names the offending input.
 */
final class InvalidAssignment extends DomainException
{
    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function unknownPeriod(int $periodId): self
    {
        return new self("Academic period [{$periodId}] does not belong to the school.", 'period_id');
    }

    public static function unknownGradeLevel(int $gradeLevelId): self
    {
        return new self("Grade level [{$gradeLevelId}] does not belong to the school.", 'grade_level_id');
    }

    public static function unknownOffer(int $offerId): self
    {
        return new self("Academic offer [{$offerId}] is not part of the period.", 'offer_id');
    }

    public static function subjectOutsidePlan(int $subjectId): self
    {
        return new self("Subject [{$subjectId}] is not an active subject of the assigned plan.", 'subject_id');
    }

    public static function noEffectivePlan(int $offerId): self
    {
        return new self("Academic offer [{$offerId}] has no effective plan.", 'offer_id');
    }
}
