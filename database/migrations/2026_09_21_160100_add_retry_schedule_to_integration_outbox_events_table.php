<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_outbox_events', function (Blueprint $table) {
            $table->timestamp('available_at')->nullable()->after('attempts')->index();
        });

        DB::table('integration_outbox_events')
            ->where('status', 'failed')
            ->where('attempts', '<', 5)
            ->update([
                'status' => 'pending',
                'available_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('integration_outbox_events', function (Blueprint $table) {
            $table->dropColumn('available_at');
        });
    }
};
