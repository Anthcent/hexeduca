<?php

namespace Modules\Users\Infrastructure\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

/**
 * Spatie roles with the team fixed to the user's own school.
 *
 * Spatie normally reads the team from a request-wide setting. A user here
 * belongs to exactly one school, so their team is always their `school_id`:
 * writes run with that team (role names resolve to the school's own roles)
 * and reads need no team filter, since no other team's rows can exist for
 * them. This keeps checks correct outside a request too (queues, console,
 * eager loading), where no request-wide team is set.
 *
 * A user without a school (the landlord super-admin) holds no roles.
 */
trait HasSchoolRoles
{
    use HasRoles {
        assignRole as private spatieAssignRole;
        removeRole as private spatieRemoveRole;
        syncRoles as private spatieSyncRoles;
        givePermissionTo as private spatieGivePermissionTo;
        revokePermissionTo as private spatieRevokePermissionTo;
        syncPermissions as private spatieSyncPermissions;
    }

    public function roles(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            app(PermissionRegistrar::class)->pivotRole
        )->withPivot(app(PermissionRegistrar::class)->teamsKey);
    }

    public function permissions(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key'),
            app(PermissionRegistrar::class)->pivotPermission
        )->withPivot(app(PermissionRegistrar::class)->teamsKey);
    }

    public function assignRole(...$roles)
    {
        return $this->inOwnSchool(fn () => $this->spatieAssignRole(...$roles));
    }

    public function removeRole(...$role)
    {
        return $this->inOwnSchool(fn () => $this->spatieRemoveRole(...$role));
    }

    public function syncRoles(...$roles)
    {
        return $this->inOwnSchool(fn () => $this->spatieSyncRoles(...$roles));
    }

    public function givePermissionTo(...$permissions)
    {
        return $this->inOwnSchool(fn () => $this->spatieGivePermissionTo(...$permissions));
    }

    public function revokePermissionTo($permission)
    {
        return $this->inOwnSchool(fn () => $this->spatieRevokePermissionTo($permission));
    }

    public function syncPermissions(...$permissions)
    {
        return $this->inOwnSchool(fn () => $this->spatieSyncPermissions(...$permissions));
    }

    private function inOwnSchool(callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($this->school_id);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
