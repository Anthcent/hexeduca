<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL migrations run transactionally. SET LOCAL bounds the
            // lock wait and restores the previous session value on completion.
            DB::statement("SET LOCAL lock_timeout = '5s'");
        }

        Schema::table('grades_enrollment_projection', function (Blueprint $table) {
            $table->unsignedBigInteger('last_event_version')->change();
        });
    }

    public function down(): void
    {
        // Narrowing this column could truncate deployed projection versions.
    }
};
