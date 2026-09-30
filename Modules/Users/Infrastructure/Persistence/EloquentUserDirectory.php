<?php

namespace Modules\Users\Infrastructure\Persistence;

use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Public\Contracts\UserDirectory;

final class EloquentUserDirectory implements UserDirectory
{
    public function namesFor(int $schoolId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $names = [];

        User::withoutTenantScope()
            ->where('school_id', $schoolId)
            ->whereIn('id', array_values(array_unique($userIds)))
            ->with('roles:id,name')
            ->get(['id', 'name'])
            ->each(function (User $user) use (&$names): void {
                $names[$user->id] = ['name' => $user->name, 'role' => $user->roles->first()?->name];
            });

        return $names;
    }
}
