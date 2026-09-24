<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            // Existing rows represent the first known aggregate state. Future
            // writes increment this durable counter under a row lock.
            $table->unsignedBigInteger('source_version')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropColumn('source_version');
        });
    }
};
