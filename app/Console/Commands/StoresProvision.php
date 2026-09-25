<?php

namespace App\Console\Commands;

use App\Models\Storefront;
use App\Stores\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the database (or PostgreSQL schema) behind each store and builds its tables.
 *
 *   php artisan stores:provision            # every store
 *   php artisan stores:provision grocery    # one store
 *
 * Safe to re-run: existing databases are kept and only missing migrations are applied.
 * Foreign keys that would point at the main database (orders.user_id and friends) are
 * dropped inside the store databases, because customers stay on the main connection.
 */
class StoresProvision extends Command
{
    protected $signature = 'stores:provision {slug? : Only this store} {--fresh : Drop and rebuild the store tables}';

    protected $description = 'Create the per-store databases/schemas and run the migrations inside them';

    public function handle(TenantManager $tenants): int
    {
        if (! $tenants->isolated()) {
            $this->error('Store isolation is off. Set STORE_ISOLATION=database (or schema) in .env first.');

            return self::FAILURE;
        }

        $stores = $tenants->stores()->when($this->argument('slug'), fn ($c) => $c->where('slug', $this->argument('slug')));
        if ($stores->isEmpty()) {
            $this->error('No matching store.');

            return self::FAILURE;
        }

        foreach ($stores as $store) {
            $this->newLine();
            $this->info($store->name.'  ('.$tenants->databaseNameFor($store).')');

            $this->createDatabase($tenants, $store);

            $connection = $tenants->register($store);
            DB::purge($connection);

            $this->call('migrate', array_filter([
                '--database' => $connection,
                '--force' => true,
                '--isolated' => false,
                '--step' => false,
            ] + ($this->option('fresh') ? ['--fresh' => true] : [])));

            $this->dropCrossDatabaseForeignKeys($connection);
        }

        $this->newLine();
        $this->info('Provisioned. Run `php artisan stores:split` to copy existing rows into the store databases.');

        return self::SUCCESS;
    }

    private function createDatabase(TenantManager $tenants, Storefront $store): void
    {
        $config = $tenants->connectionConfig($store);
        $name = $tenants->databaseNameFor($store);

        if ($config['driver'] === 'sqlite') {
            if (! file_exists($config['database'])) {
                touch($config['database']);
                $this->line('  created '.basename($config['database']));
            }

            return;
        }

        $server = $tenants->serverConnection($store);
        $connection = DB::connection($server);

        if ($tenants->mode() === 'schema') {
            $connection->statement('create schema if not exists "'.$name.'"');
            $this->line('  schema ready');

            return;
        }

        $exists = match ($config['driver']) {
            'pgsql' => (bool) $connection->selectOne('select 1 from pg_database where datname = ?', [$name]),
            default => (bool) $connection->selectOne('select 1 from information_schema.schemata where schema_name = ?', [$name]),
        };

        if (! $exists) {
            $connection->statement(match ($config['driver']) {
                'pgsql' => 'create database "'.$name.'"',
                default => 'create database `'.$name.'` character set utf8mb4 collate utf8mb4_unicode_ci',
            });
            $this->line('  database created');
        }
    }

    /** Customers live in the main database, so these constraints cannot exist inside a store database. */
    private function dropCrossDatabaseForeignKeys(string $connection): void
    {
        $driver = DB::connection($connection)->getDriverName();
        if ($driver === 'sqlite') {
            return; // SQLite connections for stores run with foreign key enforcement off
        }

        foreach (config('stores.cross_database_foreign_keys', []) as $table => $columns) {
            if (! Schema::connection($connection)->hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                foreach ($this->foreignKeyNames($connection, $table, $column) as $name) {
                    DB::connection($connection)->statement(match ($driver) {
                        'pgsql' => 'alter table "'.$table.'" drop constraint if exists "'.$name.'"',
                        default => 'alter table `'.$table.'` drop foreign key `'.$name.'`',
                    });
                    $this->line('  dropped foreign key '.$table.'.'.$column);
                }
            }
        }
    }

    private function foreignKeyNames(string $connection, string $table, string $column): array
    {
        $names = [];
        foreach (Schema::connection($connection)->getForeignKeys($table) as $key) {
            if (in_array($column, $key['columns'] ?? [], true)) {
                $names[] = $key['name'];
            }
        }

        return array_filter($names);
    }
}
