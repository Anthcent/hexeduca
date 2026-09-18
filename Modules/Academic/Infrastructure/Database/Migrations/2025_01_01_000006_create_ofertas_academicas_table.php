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
        Schema::create('ofertas_academicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('periodo_academico_id')->constrained('periodos_academicos')->restrictOnDelete();
            $table->foreignId('grado_id')->constrained('grados')->restrictOnDelete();
            $table->foreignId('seccion_id')->constrained('secciones')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('capacity');
            $table->timestamps();

            $table->unique(['periodo_academico_id', 'grado_id', 'seccion_id']);
            $table->index(['school_id', 'periodo_academico_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ofertas_academicas');
    }
};
