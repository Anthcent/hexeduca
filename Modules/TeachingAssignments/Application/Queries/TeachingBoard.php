<?php

namespace Modules\TeachingAssignments\Application\Queries;

use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Subjects\Public\Contracts\OfferSubjectsReader;
use Modules\Subjects\Public\DTOs\OfferSubjectDTO;
use Modules\TeachingAssignments\Domain\Entities\TeachingAssignment;
use Modules\TeachingAssignments\Domain\Repositories\TeachingAssignmentRepositoryInterface;
use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;
use Modules\Users\Public\Contracts\TeacherReader;
use Modules\Users\Public\DTOs\TeacherDTO;

/**
 * The period at a glance: every offer with its subjects in force and who
 * teaches each one, plus the offer's orientador and coordinator.
 */
final class TeachingBoard
{
    public function __construct(
        private readonly AcademicOfferReader $offers,
        private readonly GradeLevelReader $gradeLevels,
        private readonly OfferSubjectsReader $offerSubjects,
        private readonly TeachingAssignmentRepositoryInterface $assignments,
        private readonly TeacherReader $teachers,
    ) {}

    /**
     * @return array{offers: list<array<string, mixed>>, teachers: list<array{id: int, name: string, email: string}>}
     */
    public function forPeriod(int $schoolId, int $periodId): array
    {
        $teachers = [];

        foreach ($this->teachers->allForSchool($schoolId) as $teacher) {
            /** @var TeacherDTO $teacher */
            $teachers[$teacher->id] = ['id' => $teacher->id, 'name' => $teacher->name, 'email' => $teacher->email];
        }

        uasort($teachers, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        $bySlot = [];

        foreach ($this->assignments->activeForPeriod($schoolId, $periodId) as $assignment) {
            $bySlot[$assignment->offerId()][$assignment->subjectId()][$assignment->role()->value] = $assignment;
        }

        $coordinators = $this->assignments->coordinatorsForPeriod($schoolId, $periodId);
        $subjectsByOffer = $this->offerSubjects->forPeriod($schoolId, $periodId);

        $rows = array_map(function (AcademicOfferSummary $offer) use ($bySlot, $coordinators, $subjectsByOffer, $teachers): array {
            $subjects = array_map(fn (OfferSubjectDTO $subject): array => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'weeklyHours' => $subject->weeklyHours,
                'titular' => $this->slot($bySlot[$offer->id][$subject->id][TeachingRole::Titular->value] ?? null),
                'substitute' => $this->slot($bySlot[$offer->id][$subject->id][TeachingRole::Substitute->value] ?? null),
            ], $subjectsByOffer[$offer->id] ?? []);

            return [
                'id' => $offer->id,
                'gradeLevelId' => $offer->gradeLevelId,
                'gradeLevelName' => $offer->gradeLevelName,
                'sectionName' => $offer->sectionName,
                'orientador' => $offer->teacherId !== null ? ($teachers[$offer->teacherId] ?? null) : null,
                'coordinatorId' => $coordinators[$offer->id] ?? null,
                'subjects' => $subjects,
                'assignedCount' => count(array_filter($subjects, fn (array $s): bool => $s['titular'] !== null)),
            ];
        }, $this->orderedOffers($schoolId, $periodId));

        return ['offers' => $rows, 'teachers' => array_values($teachers)];
    }

    /**
     * @return array{assignmentId: int, teacherId: int, startedOn: string}|null
     */
    private function slot(?TeachingAssignment $assignment): ?array
    {
        return $assignment ? [
            'assignmentId' => (int) $assignment->id(),
            'teacherId' => $assignment->teacherId(),
            'startedOn' => $assignment->startedOn(),
        ] : null;
    }

    /**
     * Ordered by grade level order, then section name.
     *
     * @return list<AcademicOfferSummary>
     */
    private function orderedOffers(int $schoolId, int $periodId): array
    {
        $order = [];

        foreach ($this->gradeLevels->allForSchool($schoolId) as $gradeLevel) {
            $order[$gradeLevel->id] = $gradeLevel->order;
        }

        $offers = $this->offers->allForPeriod($schoolId, $periodId);

        usort($offers, fn (AcademicOfferSummary $a, AcademicOfferSummary $b): int => [$order[$a->gradeLevelId] ?? PHP_INT_MAX, $a->sectionName, $a->id]
            <=> [$order[$b->gradeLevelId] ?? PHP_INT_MAX, $b->sectionName, $b->id]);

        return $offers;
    }
}
