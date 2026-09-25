<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies every table from one database connection into another — the supported way to move
 * this application from MySQL (or SQLite) to PostgreSQL without hand-editing a dump.
 *
 *   php artisan migrate --database=pgsql          # create the schema first
 *   php artisan db:transfer --from=mysql --to=pgsql
 *
 * Values are converted to the target column types (booleans, JSON, numbers), foreign keys are
 * suspended during the load, and PostgreSQL identity sequences are reset afterwards.
 */
class DatabaseTransfer extends Command
{
    protected $signature = 'db:transfer
        {--from= : Source connection (default: the current default connection)}
        {--to= : Target connection}
        {--chunk=500 : Rows inserted per statement}
        {--only= : Comma-separated list of tables to copy}
        {--skip=sessions,cache,cache_locks,jobs,job_batches,failed_jobs,password_reset_tokens : Tables to leave behind}
        {--keep : Do not empty the target tables first}
        {--pretend : List what would be copied without writing}';

    protected $description = 'Copy all data from one database connection to another (e.g. MySQL → PostgreSQL)';

    public function handle(): int
    {
        $from = $this->option('from') ?: config('database.default');
        $to = $this->option('to');
        if (! $to) {
            $this->error('--to is required, e.g. --to=pgsql');

            return self::FAILURE;
        }
        if ($from === $to) {
            $this->error('Source and target connections must differ.');

            return self::FAILURE;
        }

        $source = DB::connection($from);
        $target = DB::connection($to);
        $this->info("Transferring {$from} → {$to} (".$target->getDriverName().')');

        $skip = array_filter(array_map('trim', explode(',', (string) $this->option('skip'))));
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        $sourceTables = collect(Schema::connection($from)->getTableListing())->map(fn ($t) => str_contains($t, '.') ? last(explode('.', $t)) : $t);
        $targetTables = collect(Schema::connection($to)->getTableListing())->map(fn ($t) => str_contains($t, '.') ? last(explode('.', $t)) : $t);

        $tables = $sourceTables
            ->intersect($targetTables)
            ->reject(fn ($t) => in_array($t, $skip, true))
            ->when($only, fn ($c) => $c->intersect($only))
            ->values();

        $missing = $sourceTables->reject(fn ($t) => in_array($t, $skip, true))->diff($targetTables);
        if ($missing->isNotEmpty()) {
            $this->warn('Not present in the target (run `php artisan migrate --database='.$to.'` first): '.$missing->implode(', '));
        }
        if ($tables->isEmpty()) {
            $this->error('No matching tables to copy.');

            return self::FAILURE;
        }

        if ($this->option('pretend')) {
            foreach ($tables as $table) {
                $this->line(sprintf('  %-24s %s rows', $table, number_format($source->table($table)->count())));
            }

            return self::SUCCESS;
        }

        $this->withoutForeignKeys($target, function () use ($tables, $source, $target, $to) {
            foreach ($tables as $table) {
                $this->copyTable($table, $source, $target, $to);
            }
        });

        if ($target->getDriverName() === 'pgsql') {
            $this->resetSequences($target, $tables->all());
        }

        $this->newLine();
        $this->info('Done. Point DB_CONNECTION at '.$to.' when you are ready to switch over.');

        return self::SUCCESS;
    }

    private function copyTable(string $table, $source, $target, string $to): void
    {
        $total = $source->table($table)->count();
        if (! $this->option('keep')) {
            $target->table($table)->delete();
        }
        if ($total === 0) {
            $this->line(sprintf('  %-24s empty', $table));

            return;
        }

        $casts = $this->columnCasts($to, $table);
        $key = $this->orderKey($source, $table);
        $copied = 0;

        $source->table($table)->orderBy($key)->chunk((int) $this->option('chunk'), function ($rows) use ($target, $table, $casts, &$copied) {
            $payload = $rows->map(function ($row) use ($casts) {
                $row = (array) $row;
                foreach ($row as $column => $value) {
                    $row[$column] = $this->cast($value, $casts[$column] ?? null);
                }

                return $row;
            })->all();

            $target->table($table)->insert($payload);
            $copied += count($payload);
        });

        $this->line(sprintf('  %-24s %s rows', $table, number_format($copied)));
    }

    /** Target column name => simplified type, used to convert values coming from another driver. */
    private function columnCasts(string $connection, string $table): array
    {
        $casts = [];
        foreach (Schema::connection($connection)->getColumns($table) as $column) {
            $type = strtolower($column['type_name'] ?? $column['type'] ?? '');
            $casts[$column['name']] = match (true) {
                str_contains($type, 'bool') => 'bool',
                str_contains($type, 'json') || str_contains($type, 'jsonb') => 'json',
                default => null,
            };
        }

        return $casts;
    }

    private function cast(mixed $value, ?string $type): mixed
    {
        if ($value === null || $type === null) {
            return $value;
        }
        if ($type === 'bool') {
            return (bool) $value;
        }
        // JSON arrives as a string from every driver; keep it as-is but normalise empty values.
        return $value === '' ? null : $value;
    }

    /** Chunking needs a deterministic order: the primary key when there is one, else the first column (pivot tables). */
    private function orderKey($source, string $table): string
    {
        $columns = $source->getSchemaBuilder()->getColumnListing($table);
        foreach (['id', 'key'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return $columns[0];
    }

    private function withoutForeignKeys($target, callable $callback): void
    {
        $driver = $target->getDriverName();
        match ($driver) {
            'pgsql' => $target->statement("set session_replication_role = 'replica'"),
            'mysql', 'mariadb' => $target->statement('SET FOREIGN_KEY_CHECKS=0'),
            'sqlite' => $target->statement('PRAGMA foreign_keys = OFF'),
            default => null,
        };

        try {
            $callback();
        } finally {
            match ($driver) {
                'pgsql' => $target->statement("set session_replication_role = 'origin'"),
                'mysql', 'mariadb' => $target->statement('SET FOREIGN_KEY_CHECKS=1'),
                'sqlite' => $target->statement('PRAGMA foreign_keys = ON'),
                default => null,
            };
        }
    }

    /** After a bulk load the identity sequences still start at 1 — move them past the copied ids. */
    private function resetSequences($target, array $tables): void
    {
        $this->newLine();
        $this->info('Resetting PostgreSQL sequences…');
        foreach ($tables as $table) {
            $sequence = $target->selectOne('select pg_get_serial_sequence(?, ?) as seq', [$table, 'id']);
            if (! $sequence?->seq) {
                continue;
            }
            $target->statement("select setval(?, coalesce((select max(id) from \"{$table}\"), 0) + 1, false)", [$sequence->seq]);
            $this->line('  '.$table);
        }
    }
}
