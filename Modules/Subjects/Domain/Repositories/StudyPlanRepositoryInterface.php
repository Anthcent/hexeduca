<?php

namespace Modules\Subjects\Domain\Repositories;

use Modules\Subjects\Domain\Entities\StudyPlan;

interface StudyPlanRepositoryInterface
{
    public function save(StudyPlan $plan): StudyPlan;

    public function findInSchool(int $id, int $schoolId): ?StudyPlan;

    public function delete(int $id, int $schoolId): void;

    /**
     * Every plan of the school, active and archived, keyed by id.
     *
     * @return array<int, StudyPlan>
     */
    public function allInSchool(int $schoolId): array;

    /**
     * Whether another plan of the school (active or archived) uses the code,
     * compared trimmed and case-insensitively.
     */
    public function codeTaken(int $schoolId, string $code, ?int $exceptPlanId = null): bool;
}
