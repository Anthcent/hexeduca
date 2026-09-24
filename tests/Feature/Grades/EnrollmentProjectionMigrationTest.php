<?php

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the forward migration preserves legacy projection rows and accepts versions above signed 32-bit range', function () {
    $originalConnection = DB::getDefaultConnection();
    $legacyConnection = 'grades_legacy_upgrade';

    config()->set("database.connections.{$legacyConnection}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::purge($legacyConnection);
    DB::setDefaultConnection($legacyConnection);

    try {
        Schema::create('grades_enrollment_projection', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_enrollment_id')->unique();
            $table->string('status');
            $table->unsignedInteger('last_event_version');
        });

        DB::table('grades_enrollment_projection')->insert([
            'source_enrollment_id' => 42,
            'status' => 'active',
            'last_event_version' => 7,
        ]);

        $migration = require module_path('Grades', 'Infrastructure/Database/Migrations/2026_09_23_000000_expand_grades_enrollment_projection_event_version.php');
        $migration->up();

        $preserved = DB::table('grades_enrollment_projection')->sole();
        expect($preserved->source_enrollment_id)->toBe(42)
            ->and($preserved->status)->toBe('active')
            ->and($preserved->last_event_version)->toBe(7);

        $highVersion = 2_147_483_648;
        DB::table('grades_enrollment_projection')->update(['last_event_version' => $highVersion]);

        expect(DB::table('grades_enrollment_projection')->value('last_event_version'))->toBe($highVersion);
    } finally {
        DB::purge($legacyConnection);
        DB::setDefaultConnection($originalConnection);
    }
});

test('the PostgreSQL migration grammar widens last_event_version to BIGINT', function () {
    $connection = new PostgresConnection(
        static fn () => throw new RuntimeException('The grammar test must not connect to PostgreSQL.'),
        '',
        '',
        ['driver' => 'pgsql'],
    );
    $connection->setSchemaGrammar(new PostgresGrammar($connection));

    $blueprint = new Blueprint($connection, 'grades_enrollment_projection', function (Blueprint $table) {
        $table->unsignedBigInteger('last_event_version')->change();
    });

    $sql = implode(' ', $blueprint->toSql());

    expect($sql)->toContain('alter column "last_event_version" type bigint')
        ->not->toContain('alter column "last_event_version" type integer');
});
