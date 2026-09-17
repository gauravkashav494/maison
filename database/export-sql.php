<?php

/**
 * Exports the local SQLite database as MySQL-compatible INSERT statements.
 *
 *   php database/export-sql.php > database/maison_elan_data.sql
 *
 * Import on a MySQL host AFTER running `php artisan migrate` (which creates the tables):
 *   mysql -u user -p maison_elan < database/maison_elan_data.sql
 */
$pdo = new PDO('sqlite:'.__DIR__.'/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$skip = ['migrations', 'sqlite_sequence', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

echo "-- Maison Élan data export — generated ".date('Y-m-d H:i')."\n";
echo "-- Run `php artisan migrate` first, then import this file.\n\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
    if (in_array($table, $skip, true)) {
        continue;
    }
    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    if (! $rows) {
        continue;
    }
    echo "-- {$table} (".count($rows)." rows)\n";
    echo "DELETE FROM `{$table}`;\n";
    $columns = '`'.implode('`, `', array_keys($rows[0])).'`';
    foreach ($rows as $row) {
        $values = array_map(function ($v) {
            if ($v === null) {
                return 'NULL';
            }
            if (is_int($v) || is_float($v)) {
                return (string) $v;
            }

            return "'".str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", "\\n", "\\r"], $v)."'";
        }, array_values($row));
        echo "INSERT INTO `{$table}` ({$columns}) VALUES (".implode(', ', $values).");\n";
    }
    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
