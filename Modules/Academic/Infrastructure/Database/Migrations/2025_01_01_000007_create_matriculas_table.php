<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('periodo_academico_id')->constrained('periodos_academicos')->restrictOnDelete();
            $table->foreignId('oferta_academica_id')->constrained('ofertas_academicas')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('status');
            $table->dateTime('enrolled_at');
            $table->timestamps();

            $table->unique(['oferta_academica_id', 'student_id']);
            $table->index(['school_id', 'periodo_academico_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
