<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The single-value grades of the discarded first version.
        Schema::dropIfExists('grades');

        // One evaluation plan per offer × subject × moment.
        Schema::create('grade_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('periodos_academicos')->restrictOnDelete();
            $table->foreignId('academic_offer_id')->constrained('ofertas_academicas')->restrictOnDelete();
            $table->foreignId('academic_moment_id')->constrained('momentos_academicos')->restrictOnDelete();
            // Plain ids: the subject belongs to Subjects, the author to Users.
            $table->unsignedBigInteger('study_plan_subject_id');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['academic_offer_id', 'study_plan_subject_id', 'academic_moment_id'], 'grade_plans_slot_unique');
            $table->index(['school_id', 'academic_period_id']);
        });

        // Referentes: each worth 20 points, split into indicators.
        Schema::create('grade_plan_referents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grade_plan_id')->constrained('grade_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('topic', 200);
            $table->string('technique', 200)->nullable();
            $table->timestamps();

            $table->unique(['grade_plan_id', 'position']);
        });

        Schema::create('grade_plan_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grade_plan_id')->constrained('grade_plans')->cascadeOnDelete();
            $table->foreignId('grade_plan_referent_id')->constrained('grade_plan_referents')->cascadeOnDelete();
            $table->char('letter', 1);
            $table->string('description', 300);
            $table->unsignedTinyInteger('max_points');
            $table->timestamps();

            $table->unique(['grade_plan_referent_id', 'letter']);
        });

        // Points per student and indicator. Restrict: an indicator with
        // scores can never be deleted, so no grade disappears silently.
        Schema::create('grade_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grade_plan_id')->constrained('grade_plans')->restrictOnDelete();
            $table->foreignId('grade_plan_indicator_id')->constrained('grade_plan_indicators')->restrictOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->unsignedTinyInteger('points');
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();

            $table->unique(['grade_plan_indicator_id', 'student_id']);
            $table->index(['grade_plan_id', 'student_id']);
        });

        // Extracurricular participation points, added at the end of the moment.
        Schema::create('grade_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grade_plan_id')->constrained('grade_plans')->restrictOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->unsignedTinyInteger('points');
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();

            $table->unique(['grade_plan_id', 'student_id']);
        });

        // The moment grade per student, recomputed on every change so every
        // screen and report reads the same number.
        Schema::create('grade_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grade_plan_id')->constrained('grade_plans')->restrictOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->decimal('average', 5, 2);
            $table->unsignedTinyInteger('extra')->default(0);
            $table->unsignedTinyInteger('final');
            // Every indicator has a score.
            $table->boolean('complete')->default(false);
            $table->timestamps();

            $table->unique(['grade_plan_id', 'student_id']);
        });

        // Every change to a score or an extra: who, before, after, when.
        Schema::create('grade_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grade_plan_id')->constrained('grade_plans')->restrictOnDelete();
            $table->unsignedBigInteger('student_id');
            // NULL for the extra grade.
            $table->unsignedBigInteger('grade_plan_indicator_id')->nullable();
            $table->unsignedTinyInteger('old_points')->nullable();
            $table->unsignedTinyInteger('new_points')->nullable();
            $table->unsignedBigInteger('changed_by');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['grade_plan_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_changes');
        Schema::dropIfExists('grade_results');
        Schema::dropIfExists('grade_extras');
        Schema::dropIfExists('grade_scores');
        Schema::dropIfExists('grade_plan_indicators');
        Schema::dropIfExists('grade_plan_referents');
        Schema::dropIfExists('grade_plans');
    }
};
