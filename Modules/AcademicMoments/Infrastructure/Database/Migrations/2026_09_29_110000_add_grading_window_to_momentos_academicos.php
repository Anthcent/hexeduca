<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The grade-entry window of a moment: the dates teachers may load grades.
     * Both NULL means no window has been set yet.
     */
    public function up(): void
    {
        Schema::table('momentos_academicos', function (Blueprint $table) {
            $table->date('grading_opens_on')->nullable()->after('ends_on');
            $table->date('grading_closes_on')->nullable()->after('grading_opens_on');
        });
    }

    public function down(): void
    {
        Schema::table('momentos_academicos', function (Blueprint $table) {
            $table->dropColumn(['grading_opens_on', 'grading_closes_on']);
        });
    }
};
