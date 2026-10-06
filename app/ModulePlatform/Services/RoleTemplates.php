<?php

namespace App\ModulePlatform\Services;

use App\Tenancy\Models\School;
use Nwidart\Modules\Facades\Module as NwidartModule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The default roles every school starts with. Each school owns its copies
 * (Spatie team = school) and may edit them freely afterwards, so defaults
 * are granted only when a role or a permission is first created — never
 * re-applied on a later sync, which would undo a school's edits.
 */
class RoleTemplates
{
    /**
     * Template slug => label shown to the school. The slug is the stable
     * role name; the label is what a school may rename.
     *
     * @var array<string, string>
     */
    public const TEMPLATES = [
        'director' => 'Dirección',
        'academic-control' => 'Control de Estudio',
        'administrative' => 'Administrativo',
        'teacher' => 'Docente',
        'student' => 'Estudiante',
    ];

    /**
     * Templates that hold every module permission by default; a manifest
     * only lists the other templates in a permission's `roles`.
     *
     * @var list<string>
     */
    public const FULL_ACCESS = ['director', 'academic-control'];

    public function seedAllSchools(): void
    {
        School::query()->orderBy('id')->pluck('id')->each(fn (int $schoolId) => $this->seedSchool($schoolId));
    }

    /**
     * Creates the school's missing template roles with their default
     * permissions. Existing roles are left exactly as the school has them.
     */
    public function seedSchool(int $schoolId): void
    {
        $defaults = $this->defaultPermissions();

        foreach (self::TEMPLATES as $name => $label) {
            $role = Role::query()->firstOrCreate(
                ['team_id' => $schoolId, 'name' => $name, 'guard_name' => 'web'],
                ['label' => $label],
            );

            if ($role->wasRecentlyCreated) {
                $role->permissions()->sync(
                    Permission::query()->whereIn('name', $defaults[$name] ?? [])->pluck('id')
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Grants a newly created permission to the matching template role of
     * every school.
     *
     * @param  list<string>  $templates
     */
    public function grantNewPermission(Permission $permission, array $templates): void
    {
        Role::query()
            ->whereNotNull('team_id')
            ->whereIn('name', array_values(array_unique([...self::FULL_ACCESS, ...$templates])))
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Template slug => permission names, read from every module manifest.
     *
     * @return array<string, list<string>>
     */
    private function defaultPermissions(): array
    {
        $defaults = [];

        foreach (NwidartModule::all() as $module) {
            foreach ((array) $module->get('permissions', []) as $permission) {
                $name = is_array($permission) ? ($permission['name'] ?? null) : $permission;

                if ($name === null) {
                    continue;
                }

                $templates = is_array($permission) ? (array) ($permission['roles'] ?? []) : [];

                foreach (array_unique([...self::FULL_ACCESS, ...$templates]) as $template) {
                    $defaults[$template][] = $name;
                }
            }
        }

        return $defaults;
    }
}
