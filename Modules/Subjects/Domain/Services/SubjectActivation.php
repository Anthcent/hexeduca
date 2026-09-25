<?php

namespace Modules\Subjects\Domain\Services;

use Modules\Subjects\Domain\Entities\Subject;

/**
 * Which subjects of the effective plan an offer studies: the plan's active
 * subjects of the offer's grade level are active by default, minus the
 * ones excluded on the effective assignment. Archived subjects are out of
 * circulation and never listed.
 */
final class SubjectActivation
{
    /**
     * @param  iterable<Subject>  $planSubjects
     * @param  list<int>  $excludedSubjectIds
     * @return list<array{subject: Subject, active: bool}>
     */
    public static function forGradeLevel(iterable $planSubjects, int $gradeLevelId, array $excludedSubjectIds): array
    {
        $excluded = array_flip($excludedSubjectIds);
        $rows = [];

        foreach ($planSubjects as $subject) {
            if ($subject->isArchived() || $subject->gradeLevelId() !== $gradeLevelId) {
                continue;
            }

            $rows[] = ['subject' => $subject, 'active' => ! isset($excluded[$subject->id()])];
        }

        return $rows;
    }
}
