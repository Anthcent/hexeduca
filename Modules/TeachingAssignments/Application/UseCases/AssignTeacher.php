<?php

namespace Modules\TeachingAssignments\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Subjects\Public\Contracts\OfferSubjectsReader;
use Modules\Subjects\Public\DTOs\OfferSubjectDTO;
use Modules\TeachingAssignments\Application\DTOs\AssignTeacherData;
use Modules\TeachingAssignments\Application\Services\PeriodGuard;
use Modules\TeachingAssignments\Domain\Entities\TeachingAssignment;
use Modules\TeachingAssignments\Domain\Exceptions\InvalidTeachingAssignment;
use Modules\TeachingAssignments\Domain\Exceptions\PeriodClosed;
use Modules\TeachingAssignments\Domain\Repositories\TeachingAssignmentRepositoryInterface;
use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;
use Modules\Users\Public\Contracts\TeacherReader;

/**
 * Puts a teacher on a subject of an offer. The slot's current holder, if
 * any, is ended (kept as history) and the new assignment starts today.
 */
final class AssignTeacher
{
    public function __construct(
        private readonly TeachingAssignmentRepositoryInterface $assignments,
        private readonly PeriodGuard $guard,
        private readonly OfferSubjectsReader $offerSubjects,
        private readonly TeacherReader $teachers,
    ) {}

    /**
     * @throws InvalidTeachingAssignment
     * @throws PeriodClosed
     */
    public function handle(AssignTeacherData $data): TeachingAssignment
    {
        $this->guard->openPeriod($data->periodId, $data->schoolId);
        $this->guard->offerInPeriod($data->offerId, $data->periodId, $data->schoolId);

        $inForce = array_map(
            fn (OfferSubjectDTO $subject): int => $subject->id,
            $this->offerSubjects->forOffer($data->schoolId, $data->periodId, $data->offerId),
        );

        if (! in_array($data->subjectId, $inForce, true)) {
            throw InvalidTeachingAssignment::subjectNotInForce($data->subjectId);
        }

        if ($this->teachers->findForSchool($data->teacherId, $data->schoolId) === null) {
            throw InvalidTeachingAssignment::unknownTeacher($data->teacherId);
        }

        $otherRole = $data->role === TeachingRole::Titular ? TeachingRole::Substitute : TeachingRole::Titular;
        $today = now()->toDateString();

        return DB::transaction(function () use ($data, $otherRole, $today): TeachingAssignment {
            $other = $this->assignments->activeInSlot($data->schoolId, $data->offerId, $data->subjectId, $otherRole);

            if ($other !== null && $other->teacherId() === $data->teacherId) {
                throw InvalidTeachingAssignment::sameTeacherInOtherRole($data->teacherId);
            }

            $holder = $this->assignments->activeInSlot($data->schoolId, $data->offerId, $data->subjectId, $data->role);

            if ($holder !== null && $holder->teacherId() === $data->teacherId) {
                return $holder;
            }

            if ($holder !== null) {
                $this->assignments->save($holder->end($today));
            }

            return $this->assignments->save(TeachingAssignment::start(
                $data->schoolId,
                $data->periodId,
                $data->offerId,
                $data->subjectId,
                $data->teacherId,
                $data->role,
                $today,
            ));
        });
    }
}
