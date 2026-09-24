<?php

namespace Modules\Grades\Application\UseCases;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferProjectionSource;
use Modules\Grades\Application\DTOs\RecordGradeData;
use Modules\Grades\Domain\Entities\Grade;
use Modules\Grades\Domain\Repositories\GradeRepositoryInterface;
use Modules\Users\Public\Contracts\TeacherReader;

/**
 * Validates the student is actually enrolled in the target AcademicOffer
 * by reading `grades_enrollment_projection` — Grades' own local copy,
 * never a live query against Modules\Enrollments (plan §6/§8). school_id
 * membership is constrained by the trusted offer plus the actor's school
 * and active period. The assigned teacher comes from the offer, never from
 * an unchecked request value.
 */
final class RecordGrade
{
    public function __construct(
        private readonly GradeRepositoryInterface $grades,
        private readonly AcademicOfferProjectionSource $academicOffers,
        private readonly TeacherReader $teachers,
    ) {}

    public function handle(RecordGradeData $data): Grade
    {
        $academicOffer = $this->academicOffers->find($data->academicOfferId);

        if ($academicOffer === null
            || $academicOffer->schoolId !== $data->actorSchoolId
            || $academicOffer->academicPeriodId !== $data->activeAcademicPeriodId) {
            throw new AuthorizationException('The AcademicOffer is not available in the actor school and active period.');
        }

        if ($academicOffer->teacherId === null || $academicOffer->teacherId !== $data->teacherId) {
            throw new AuthorizationException('Grades may only be recorded for the teacher assigned to the AcademicOffer.');
        }

        if (! $data->actorCanDelegate && $data->actorId !== $data->teacherId) {
            throw new AuthorizationException('A teacher may not record a grade for another teacher.');
        }

        if ($this->teachers->findForSchool($data->teacherId, $data->actorSchoolId) === null) {
            throw new AuthorizationException('The assigned teacher must be a teacher in the actor school.');
        }

        $enrollment = DB::table('grades_enrollment_projection')
            ->where('academic_offer_id', $data->academicOfferId)
            ->where('student_id', $data->studentId)
            ->where('school_id', $data->actorSchoolId)
            ->where('academic_period_id', $data->activeAcademicPeriodId)
            ->first();

        if ($enrollment === null) {
            throw new DomainException('The student is not enrolled in this AcademicOffer.');
        }

        if ($enrollment->status !== 'active') {
            throw new DomainException('The student\'s enrollment in this AcademicOffer is not active.');
        }

        $grade = new Grade(
            id: null,
            schoolId: $data->actorSchoolId,
            academicPeriodId: $data->activeAcademicPeriodId,
            academicOfferId: $data->academicOfferId,
            studentId: $data->studentId,
            teacherId: $data->teacherId,
            value: $data->value,
        );

        return $this->grades->save($grade);
    }
}
