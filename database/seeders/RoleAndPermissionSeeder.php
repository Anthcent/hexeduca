<?php

namespace Database\Seeders;

use App\ModulePlatform\Services\RoleTemplates;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Gives every existing school its missing template roles. Roles are per
     * school (Spatie team = school) and a new school gets them on creation,
     * so this only backfills; it never touches a school's edited roles.
     * Permissions come from the module manifests (`modules:sync`).
     */
    public function run(RoleTemplates $templates): void
    {
        $templates->seedAllSchools();
    }
}
