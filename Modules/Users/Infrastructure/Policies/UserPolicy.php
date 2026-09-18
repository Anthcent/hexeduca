<?php

namespace Modules\Users\Infrastructure\Policies;

use Modules\Users\Infrastructure\Models\User;

/**
 * See design.md ("Decision: super-admin escalation guarded by a UserPolicy
 * (not inline, not Gate closure)") for the full rationale.
 *
 * Registered explicitly via `Gate::policy(User::class, UserPolicy::class)`
 * in `UsersServiceProvider::boot()` — the `User` model lives outside
 * `App\Models`, so Laravel's policy auto-discovery will not find it.
 */
class UserPolicy
{
    /**
     * Determine whether `$actor` may assign `$role` to `$target`.
     *
     * `$actor->school_id === $target->school_id` is the enforcement
     * mechanism that always applies, on any host, regardless of whether
     * `TenantScope` happens to be scoping the query that loaded `$target`
     * (it only does so when a tenant is bound to the current request).
     */
    public function assignRole(User $actor, User $target, string $role): bool
    {
        if ($actor->hasRole('super-admin')) {
            return true;
        }

        return $actor->school_id !== null
            && $actor->school_id === $target->school_id
            && $role !== 'super-admin';
    }
}
