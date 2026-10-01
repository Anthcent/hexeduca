<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separates who a person is (type) from what they may do (roles), and
     * backfills the type from the role each user holds today.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('type', 20)->default('staff')->after('email');
            $table->index(['school_id', 'type']);
        });

        // Teacher runs last so it wins for anyone holding both roles: losing
        // a teacher would drop their assignments from the projection.
        foreach (['student', 'teacher'] as $role) {
            $userIds = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', $role)
                ->pluck('model_has_roles.model_id');

            DB::table('users')->whereIn('id', $userIds)->update(['type' => $role]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'type']);
            $table->dropColumn('type');
        });
    }
};
