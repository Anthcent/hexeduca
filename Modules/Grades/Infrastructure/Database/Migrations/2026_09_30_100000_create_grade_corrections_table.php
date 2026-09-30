<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Correction mode: staff reopens one plan for grade changes outside
        // its grading window (or in a closed period), with a reason and an
        // expiry. Rows are kept as history; closing sets closed_at.
        Schema::create('grade_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grade_plan_id')->constrained('grade_plans')->restrictOnDelete();
            $table->unsignedBigInteger('opened_by');
            $table->string('reason', 500);
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->index(['grade_plan_id', 'closed_at']);
        });

        Schema::table('grade_changes', function (Blueprint $table) {
            // Set when the change was made under a correction.
            $table->foreignId('grade_correction_id')->nullable()->after('changed_by')->constrained('grade_corrections')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grade_changes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_correction_id');
        });

        Schema::dropIfExists('grade_corrections');
    }
};
