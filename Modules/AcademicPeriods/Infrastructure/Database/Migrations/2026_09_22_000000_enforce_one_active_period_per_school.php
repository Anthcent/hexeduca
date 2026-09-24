<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL and SQLite both support partial unique indexes. This is
        // the database backstop for writes that bypass ActivateAcademicPeriod.
        DB::statement(
            'CREATE UNIQUE INDEX periodos_academicos_one_active_per_school '
            .'ON periodos_academicos (school_id) WHERE is_active = true'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS periodos_academicos_one_active_per_school');
    }
};
