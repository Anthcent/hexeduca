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
        Schema::create('integration_outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name');
            $table->string('event_class');
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('failed_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_outbox_events');
    }
};
