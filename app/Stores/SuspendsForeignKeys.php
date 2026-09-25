<?php

namespace App\Stores;

use Illuminate\Database\Connection;

/** Loads rows in any order by switching foreign key enforcement off for the duration. */
trait SuspendsForeignKeys
{
    protected function withoutForeignKeys(Connection $connection, callable $callback): mixed
    {
        $driver = $connection->getDriverName();
        $toggle = fn (bool $on) => match ($driver) {
            'pgsql' => $connection->statement("set session_replication_role = '".($on ? 'origin' : 'replica')."'"),
            'mysql', 'mariadb' => $connection->statement('SET FOREIGN_KEY_CHECKS='.($on ? 1 : 0)),
            'sqlite' => $connection->statement('PRAGMA foreign_keys = '.($on ? 'ON' : 'OFF')),
            default => null,
        };

        $toggle(false);

        try {
            return $callback();
        } finally {
            $toggle(true);
        }
    }
}
