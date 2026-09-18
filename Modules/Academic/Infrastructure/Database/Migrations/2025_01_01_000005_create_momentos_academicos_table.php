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
        Schema::create('momentos_academicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_academico_id')->constrained('periodos_academicos')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('order');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index('periodo_academico_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('momentos_academicos');
    }
};
