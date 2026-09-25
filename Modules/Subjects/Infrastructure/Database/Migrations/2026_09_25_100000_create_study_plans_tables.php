<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            // Official plan code. Not unique: a school may repeat it, and the
            // observation tells those plans apart.
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'code']);
        });

        Schema::create('study_plan_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('study_plan_id')->constrained('study_plans')->restrictOnDelete();
            // The plan's year: a grade level of the school.
            $table->foreignId('grade_level_id')->constrained('grados')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->unsignedSmallInteger('weekly_hours')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['study_plan_id', 'grade_level_id']);
        });

        Schema::create('study_plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('periodos_academicos')->restrictOnDelete();
            $table->foreignId('study_plan_id')->constrained('study_plans')->restrictOnDelete();
            $table->string('scope', 20);
            // Set for the grade_level scope and, copied from the offer, for
            // the offer scope. NULL for the school scope.
            $table->foreignId('grade_level_id')->nullable()->constrained('grados')->restrictOnDelete();
            $table->foreignId('academic_offer_id')->nullable()->constrained('ofertas_academicas')->cascadeOnDelete();
            // Normalized slot target: 0 (school), the grade level id, or the
            // offer id. Never NULL, so the unique index below compares it.
            $table->unsignedBigInteger('target_id');
            // Set when another assignment took the slot while this one's plan
            // was archived; kept as history a reactivation may restore.
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_period_id']);
            $table->index(['study_plan_id']);
        });

        // At most one CURRENT assignment per (period, scope, target). Partial
        // index: replaced rows are history and may repeat a slot. Supported
        // by PostgreSQL and SQLite alike.
        DB::statement(
            'CREATE UNIQUE INDEX study_plan_assignments_current_slot_unique '
            .'ON study_plan_assignments (school_id, academic_period_id, scope, target_id) '
            .'WHERE replaced_at IS NULL'
        );

        Schema::create('study_plan_subject_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('study_plan_assignment_id')->constrained('study_plan_assignments')->cascadeOnDelete();
            $table->foreignId('study_plan_subject_id')->constrained('study_plan_subjects')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['study_plan_assignment_id', 'study_plan_subject_id'], 'study_plan_exclusions_unique');
            $table->index('study_plan_subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plan_subject_exclusions');
        Schema::dropIfExists('study_plan_assignments');
        Schema::dropIfExists('study_plan_subjects');
        Schema::dropIfExists('study_plans');
    }
};
