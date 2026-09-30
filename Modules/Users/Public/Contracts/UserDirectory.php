<?php

namespace Modules\Users\Public\Contracts;

/**
 * Display names of a school's users, whatever their role: for audit trails
 * that must say who did something (a teacher, staff member, ...).
 */
interface UserDirectory
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, array{name: string, role: ?string}> keyed by user id; unknown ids are left out
     */
    public function namesFor(int $schoolId, array $userIds): array;
}
