<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local projection owned by Enrollments (plan §6, deferred from Fase 6 to
 * Fase 8). Populated by ProjectStudentListener reacting to
 * Modules\Users\Public\Events\{UserCreated,UserUpdated} via the outbox.
 * Enrollments never queries Modules\Users\Infrastructure\Models directly
 * for its student picker — see EnrollmentController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments_student_projection', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_student_id')->unique();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->timestamp('source_updated_at');
            $table->unsignedInteger('last_event_version');
            $table->timestamps();

            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments_student_projection');
    }
};
