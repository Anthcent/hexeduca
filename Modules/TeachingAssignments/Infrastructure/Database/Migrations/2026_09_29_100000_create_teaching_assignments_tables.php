<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('periodos_academicos')->restrictOnDelete();
            $table->foreignId('academic_offer_id')->constrained('ofertas_academicas')->restrictOnDelete();
            // Plain ids, no FK: the subject belongs to Subjects and the teacher
            // to Users. Their own rules decide when they can go; an ended
            // assignment is history and must not block them.
            $table->unsignedBigInteger('study_plan_subject_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('role', 20);
            $table->date('started_on');
            // Set when the assignment is replaced or removed. Never deleted.
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_period_id']);
            $table->index(['school_id', 'teacher_id']);
            $table->index(['academic_offer_id', 'study_plan_subject_id']);
        });

        // At most one ACTIVE assignment per (offer, subject, role). Ended rows
        // are history and may repeat. Partial index: PostgreSQL and SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX teaching_assignments_active_unique '
            .'ON teaching_assignments (academic_offer_id, study_plan_subject_id, role) '
            .'WHERE ended_on IS NULL'
        );

        // The coordinator of an offer. The homeroom teacher (orientador) is
        // the offer's own teacher, owned by AcademicOffers.
        Schema::create('teaching_offer_coordinators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('periodos_academicos')->restrictOnDelete();
            $table->foreignId('academic_offer_id')->unique()->constrained('ofertas_academicas')->cascadeOnDelete();
            $table->unsignedBigInteger('teacher_id');
            $table->timestamps();

            $table->index(['school_id', 'academic_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_offer_coordinators');
        Schema::dropIfExists('teaching_assignments');
    }
};
