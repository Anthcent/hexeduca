<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_outbox_events', function (Blueprint $table) {
            $table->uuid('claim_token')->nullable()->after('status')->index();
            $table->timestamp('claimed_at')->nullable()->after('claim_token')->index();
        });
    }

    public function down(): void
    {
        Schema::table('integration_outbox_events', function (Blueprint $table) {
            $table->dropColumn(['claim_token', 'claimed_at']);
        });
    }
};
