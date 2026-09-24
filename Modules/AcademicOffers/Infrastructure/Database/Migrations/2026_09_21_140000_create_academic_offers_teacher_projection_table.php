<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local projection owned by AcademicOffers (plan §6, deferred from Fase 6
 * to Fase 8). Populated by ProjectTeacherListener reacting to
 * Modules\Users\Public\Events\{UserCreated,UserUpdated} via the outbox.
 * AcademicOffers never queries Modules\Users\Infrastructure\Models
 * directly for its teacher picker — see AcademicOfferController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_offers_teacher_projection', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_teacher_id')->unique();
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
        Schema::dropIfExists('academic_offers_teacher_projection');
    }
};
