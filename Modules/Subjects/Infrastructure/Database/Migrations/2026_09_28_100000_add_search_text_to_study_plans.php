<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_plans', function (Blueprint $table) {
            // Code, name and observation folded to lowercase ASCII, for an
            // accent-insensitive search on any database. Kept in sync on save.
            $table->text('search_text')->nullable();
        });

        DB::table('study_plans')->orderBy('id')->chunkById(200, function ($plans): void {
            foreach ($plans as $plan) {
                DB::table('study_plans')->where('id', $plan->id)->update([
                    'search_text' => strtolower(Str::ascii($plan->code.' '.$plan->name.' '.$plan->observation)),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('study_plans', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
