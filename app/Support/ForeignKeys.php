<?php

namespace App\Support;

use Illuminate\Database\Connection;

/**
 * Loads data into a database without tripping over the order of its foreign keys.
 *
 * Suspending key checks needs no privileges on MySQL and SQLite; on PostgreSQL it needs a
 * superuser, so when that fails the caller is told and loads the tables parents-first
 * instead. The keys are always restored, including when the callback throws.
 */
class ForeignKeys
{
    /** @return bool whether the keys were actually suspended */
    public static function suspended(Connection $connection, callable $callback, ?callable $onFailure = null): bool
    {
        $driver = $connection->getDriverName();
        $suspended = true;

        try {
            self::toggle($connection, $driver, false);
        } catch (\Throwable $e) {
            $suspended = false;
            $onFailure && $onFailure($e);
        }

        try {
            $callback();
        } finally {
            if ($suspended) {
                self::toggle($connection, $driver, true);
            }
        }

        return $suspended;
    }

    private static function toggle(Connection $connection, string $driver, bool $on): void
    {
        match ($driver) {
            'pgsql' => $connection->statement("set session_replication_role = '".($on ? 'origin' : 'replica')."'"),
            'mysql', 'mariadb' => $connection->statement('SET FOREIGN_KEY_CHECKS='.($on ? '1' : '0')),
            'sqlite' => $connection->statement('PRAGMA foreign_keys = '.($on ? 'ON' : 'OFF')),
            default => null,
        };
    }
}
