<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convivir: one letter per student, offer and moment, given by the
        // offer's homeroom teacher (orientador). It is not part of any grade.
        Schema::create('grade_conduct', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedBigInteger('academic_offer_id');
            $table->unsignedBigInteger('academic_moment_id');
            $table->unsignedBigInteger('student_id');
            $table->string('letter', 2);
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();

            $table->unique(['academic_offer_id', 'academic_moment_id', 'student_id']);
            $table->index(['school_id', 'academic_offer_id', 'academic_moment_id']);
        });

        // Every change to a Convivir letter: who, before, after, when.
        Schema::create('grade_conduct_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedBigInteger('academic_offer_id');
            $table->unsignedBigInteger('academic_moment_id');
            $table->unsignedBigInteger('student_id');
            $table->string('old_letter', 2)->nullable();
            $table->string('new_letter', 2)->nullable();
            $table->unsignedBigInteger('changed_by');
            $table->timestamp('created_at')->nullable();

            $table->index(['academic_offer_id', 'academic_moment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_conduct_changes');
        Schema::dropIfExists('grade_conduct');
    }
};
