<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path')->unique();
            // Nullable so deleting the uploader keeps the files; the name
            // snapshot below still tells who uploaded them.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_name');
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_files');
    }
};
