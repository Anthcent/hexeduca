<?php

use App\ModulePlatform\Services\RoleTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global roles become per-school roles (Spatie teams, team = school) and
     * the super-admin becomes a user flag, since it belongs to no school.
     *
     * A fresh database already gets the team columns from the permission
     * tables migration (teams is now on); only a database created before
     * this change needs the structure upgraded and its data moved.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('type');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->string('label')->nullable()->after('name');
        });

        if (Schema::hasColumn('model_has_roles', 'team_id')) {
            return;
        }

        // Read before the pivots are rebuilt: a landlord super-admin has no
        // school, so their role row does not survive the rebuild.
        $superAdminIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super-admin')
            ->pluck('model_has_roles.model_id');
        DB::table('users')->whereIn('id', $superAdminIds)->update(['is_super_admin' => true]);

        $this->addTeamsToPermissionTables();
        $this->moveGlobalRolesToSchools();
    }

    public function down(): void
    {
        // Per-school roles cannot be folded back into global ones without
        // losing each school's edits; restore from a backup instead.
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('label');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_super_admin');
        });
    }

    private function addTeamsToPermissionTables(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id')->nullable()->after('id');
            $table->index('team_id', 'roles_team_foreign_key_index');
            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['team_id', 'name', 'guard_name']);
        });

        // The team joins the primary key, which SQLite cannot alter in
        // place, so both pivots are rebuilt: their rows are read with the
        // owning user's school as the team, the table is recreated with the
        // same names a fresh install gets, and the rows go back in.
        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $pivot => $key) {
            $related = $key === 'role_id' ? 'roles' : 'permissions';

            $rows = DB::table($pivot)
                ->join('users', 'users.id', '=', "{$pivot}.model_id")
                ->whereNotNull('users.school_id')
                ->orderBy("{$pivot}.model_id")
                ->get(["{$pivot}.{$key}", "{$pivot}.model_type", "{$pivot}.model_id", 'users.school_id'])
                ->map(fn ($row): array => [
                    $key => $row->{$key},
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                    'team_id' => $row->school_id,
                ]);

            Schema::drop($pivot);

            Schema::create($pivot, function (Blueprint $table) use ($pivot, $key, $related): void {
                $table->unsignedBigInteger($key);
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->unsignedBigInteger('team_id');
                $table->index(['model_id', 'model_type'], "{$pivot}_model_id_model_type_index");
                $table->index('team_id', "{$pivot}_team_foreign_key_index");
                $table->foreign($key)->references('id')->on($related)->cascadeOnDelete();
                $table->primary(['team_id', $key, 'model_id', 'model_type'], "{$pivot}_{$key}_model_type_primary");
            });

            $rows->chunk(500)->each(fn ($chunk) => DB::table($pivot)->insert($chunk->all()));
        }
    }

    /**
     * Everyone moves from the old global role to the matching template role
     * of their own school, and the global roles (super-admin included) are
     * dropped.
     */
    private function moveGlobalRolesToSchools(): void
    {
        app(RoleTemplates::class)->seedAllSchools();

        $templateFor = ['staff/admin' => 'director', 'teacher' => 'teacher', 'student' => 'student'];

        foreach ($templateFor as $legacyName => $template) {
            $legacyId = DB::table('roles')->whereNull('team_id')->where('name', $legacyName)->value('id');

            if ($legacyId === null) {
                continue;
            }

            DB::table('model_has_roles')->where('role_id', $legacyId)->orderBy('model_id')->get()
                ->each(function ($row) use ($legacyId, $template): void {
                    $schoolRoleId = DB::table('roles')
                        ->where('team_id', $row->team_id)
                        ->where('name', $template)
                        ->value('id');

                    DB::table('model_has_roles')
                        ->where('role_id', $legacyId)
                        ->where('model_id', $row->model_id)
                        ->update(['role_id' => $schoolRoleId]);
                });
        }

        DB::table('roles')->whereNull('team_id')->delete();
    }
};
