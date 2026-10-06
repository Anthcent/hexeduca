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
 * and reads keep only the rows whose team is the owner's school, compared
 * in SQL rather than against a request-wide team. This keeps checks correct
 * outside a request too (queues, console, eager loading), and a stray row
 * from another school (e.g. after a user changes school) grants nothing.
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
        $pivot = config('permission.table_names.model_has_roles');
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        // The role itself must belong to that school too, so a role object
        // from another school attached by mistake grants nothing.
        return $this->ownSchoolRows(config('permission.models.role'), $pivot, app(PermissionRegistrar::class)->pivotRole)
            ->whereColumn(config('permission.table_names.roles').".{$teamsKey}", "{$pivot}.{$teamsKey}");
    }

    public function permissions(): BelongsToMany
    {
        return $this->ownSchoolRows(
            config('permission.models.permission'),
            config('permission.table_names.model_has_permissions'),
            app(PermissionRegistrar::class)->pivotPermission
        );
    }

    /**
     * The pivot rows whose team is the owning user's school. The owner is
     * matched in SQL, so the same filter holds when eager loading, where the
     * relation is built from an empty model with no school_id of its own.
     */
    private function ownSchoolRows(string $related, string $pivot, string $relatedPivotKey): BelongsToMany
    {
        $morphKey = config('permission.column_names.model_morph_key');
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;
        $users = $this->getTable();

        return $this->morphToMany($related, 'model', $pivot, $morphKey, $relatedPivotKey)
            ->withPivot($teamsKey)
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from($users)
                ->whereColumn("{$users}.{$this->getKeyName()}", "{$pivot}.{$morphKey}")
                ->whereColumn("{$users}.school_id", "{$pivot}.{$teamsKey}"));
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
