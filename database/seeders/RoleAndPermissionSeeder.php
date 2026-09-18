<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Base roles for the multi-tenant school management system.
     *
     * Roles are GLOBAL across all tenants (Spatie `permission.teams` stays
     * `false`): a role label such as "teacher" carries the same meaning at
     * every school. Tenant isolation of people and their data is enforced
     * via `users.school_id` and the `BelongsToTenant` scope, not by
     * per-tenant role rows — a "teacher" at school 1 and a "teacher" at
     * school 2 are distinct user records, each only ever seeing their own
     * school's data.
     *
     * Scaffolding only — no business-logic permissions are attached here.
     * Future feature changes will attach permissions to these roles as
     * modules gain real functionality.
     *
     * @var list<string>
     */
    private const ROLES = [
        'student',
        'teacher',
        'staff/admin',
        'super-admin',
    ];

    /**
     * Seed the application's base roles.
     */
    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }
    }
}
