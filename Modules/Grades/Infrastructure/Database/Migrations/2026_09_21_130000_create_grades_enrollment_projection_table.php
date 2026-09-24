<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local projection owned by Grades (plan §6/§8). Populated exclusively by
 * ProjectEnrollmentListener reacting to Modules\Enrollments\Public\Events\
 * EnrollmentCreated (via the outbox) or by `grades:rebuild-enrollments`
 * (via Modules\Enrollments\Public\Contracts\EnrollmentProjectionSource).
 * Grades never queries Modules\Enrollments\Infrastructure\Models directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades_enrollment_projection', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_enrollment_id')->unique();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_offer_id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('academic_period_id');
            $table->string('status');
            $table->timestamp('source_updated_at');
            $table->unsignedBigInteger('last_event_version');
            $table->timestamps();

            $table->index(['school_id', 'academic_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades_enrollment_projection');
    }
};
