<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grades' own domain table (Fase 8 — first real domain, not just the
 * projection scaffolding from Fase 7). `school_id`/`periodo_academico_id`
 * are the one sanctioned vertical dependency (Tenant -> Periodo, plan §1);
 * `academic_offer_id`/`student_id`/`teacher_id` are plain foreign ids with
 * no Eloquent relation crossing into AcademicOffers/Enrollments/Users —
 * enrollment membership is checked against `grades_enrollment_projection`
 * (this module's own local copy), never a live cross-module query.
 *
 * Column is named `periodo_academico_id` (not `academic_period_id`)
 * because App\AcademicPeriod\Scopes\AcademicPeriodScope /
 * Concerns\BelongsToActivePeriod hardcode that physical column name — see
 * their docblocks (Fase 1 decision: platform scope, not renamed per table).
 * This is a brand-new table, so the name looks inconsistent with the rest
 * of this module's English naming, but matching it is what makes the
 * `BelongsToActivePeriod` trait actually filter/stamp correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('periodo_academico_id');
            $table->unsignedBigInteger('academic_offer_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('teacher_id');
            $table->decimal('value', 5, 2);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['school_id', 'periodo_academico_id', 'academic_offer_id']);
            $table->index(['academic_offer_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
