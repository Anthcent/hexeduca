<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments_student_projection', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('email')->index();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments_student_projection', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
