<?php

namespace Modules\Grades\Domain\Repositories;

interface ConductBookRepositoryInterface
{
    /**
     * Sets (or clears, with null) a student's Convivir letter for an offer
     * and moment, logging the change. Returns the previous letter.
     */
    public function setLetter(int $schoolId, int $offerId, int $momentId, int $studentId, ?string $letter, int $actorId): ?string;

    /**
     * @return array<int, string> student id => letter
     */
    public function letters(int $schoolId, int $offerId, int $momentId): array;

    /**
     * Whether the student's letter changed after its first entry.
     */
    public function isEdited(int $schoolId, int $offerId, int $momentId, int $studentId): bool;

    /**
     * Students whose letter changed after its first entry.
     *
     * @return list<int>
     */
    public function editedStudents(int $schoolId, int $offerId, int $momentId): array;
}
