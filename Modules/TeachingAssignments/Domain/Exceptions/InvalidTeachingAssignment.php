<?php

namespace Modules\TeachingAssignments\Domain\Exceptions;

use DomainException;

/**
 * A request that names something outside the school or the period. `field`
 * is the form field the message belongs to.
 */
final class InvalidTeachingAssignment extends DomainException
{
    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function unknownPeriod(int $periodId): self
    {
        return new self("Academic period [{$periodId}] does not belong to the school.", 'period_id');
    }

    public static function unknownOffer(int $offerId): self
    {
        return new self("Academic offer [{$offerId}] is not part of the period.", 'offer_id');
    }

    public static function subjectNotInForce(int $subjectId): self
    {
        return new self("Subject [{$subjectId}] is not in force for the offer.", 'subject_id');
    }

    public static function unknownTeacher(int $teacherId): self
    {
        return new self("Teacher [{$teacherId}] does not belong to the school.", 'teacher_id');
    }

    public static function sameTeacherInOtherRole(int $teacherId): self
    {
        return new self("Teacher [{$teacherId}] already holds the other role for this subject.", 'teacher_id');
    }
}
