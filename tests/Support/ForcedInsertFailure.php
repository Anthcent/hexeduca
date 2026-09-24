<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Installs a BEFORE INSERT trigger that makes every matching insert fail, so
 * tests can prove that a write rolls back together with its transaction.
 *
 * The trigger is driver-aware: SQLite uses RAISE(ABORT), PostgreSQL uses a
 * plpgsql function that RAISEs EXCEPTION. Both drivers run DDL inside the
 * RefreshDatabase transaction, so the trigger (and the PostgreSQL function)
 * is rolled back with the rest of the test data; no manual cleanup is needed.
 */
final class ForcedInsertFailure
{
    /**
     * @param  string|null  $eventName  When set, only inserts whose `event_name` column
     *                                  equals this value fail (for the integration outbox).
     */
    public static function install(string $trigger, string $table, string $message, ?string $eventName = null): void
    {
        foreach ([$trigger, $table] as $identifier) {
            if (! preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
                throw new InvalidArgumentException("Unsafe SQL identifier [{$identifier}].");
            }
        }

        $connection = DB::connection();
        $quotedMessage = $connection->getPdo()->quote($message);
        $when = $eventName === null
            ? null
            : 'NEW.event_name = '.$connection->getPdo()->quote($eventName);

        match ($connection->getDriverName()) {
            'sqlite' => $connection->statement(sprintf(
                'CREATE TRIGGER %s BEFORE INSERT ON %s %s BEGIN SELECT RAISE(ABORT, %s); END',
                $trigger,
                $table,
                $when === null ? '' : "WHEN {$when}",
                $quotedMessage,
            )),
            'pgsql' => self::installPostgres($trigger, $table, $quotedMessage, $when),
            default => throw new RuntimeException(
                "ForcedInsertFailure does not support the [{$connection->getDriverName()}] driver."
            ),
        };
    }

    private static function installPostgres(string $trigger, string $table, string $quotedMessage, ?string $when): void
    {
        $function = "{$trigger}_fn";

        DB::statement(sprintf(
            'CREATE FUNCTION %s() RETURNS trigger LANGUAGE plpgsql AS $fn$ BEGIN RAISE EXCEPTION \'%%\', %s; END; $fn$',
            $function,
            $quotedMessage,
        ));

        DB::statement(sprintf(
            'CREATE TRIGGER %s BEFORE INSERT ON %s FOR EACH ROW %s EXECUTE FUNCTION %s()',
            $trigger,
            $table,
            $when === null ? '' : "WHEN ({$when})",
            $function,
        ));
    }
}
