<?php

namespace App\Console\Commands;

use App\Models\Storefront;
use App\Stores\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a store's database from the store's own definition.
 *
 *   php artisan stores:provision              # every store
 *   php artisan stores:provision grocery      # one store
 *   php artisan stores:provision --fresh      # drop the store tables and rebuild them
 *   php artisan stores:provision --no-seed    # schema only, no content
 *
 * For each store it creates the database (or PostgreSQL schema), builds the store schema from
 * database/migrations/tenant, and fills it with that store's own content by running the seeders
 * its template declares. Nothing is copied out of another database: each store is created from
 * its own catalogue, pages, navigation and settings.
 *
 * Re-running is safe — the schema step applies only missing migrations and the seeders are
 * written to update in place rather than duplicate.
 */
class StoresProvision extends Command
{
    protected $signature = 'stores:provision
        {slug? : Only this store}
        {--fresh : Drop the store tables first and rebuild them}
        {--no-seed : Create the schema without any content}';

    protected $description = 'Create each store database and build it from that store\'s own schema and content';

    public const TENANT_MIGRATIONS = 'database/migrations/tenant';

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

        $summary = [];

        foreach ($stores as $store) {
            $this->newLine();
            $this->info($store->name.'  ('.$tenants->databaseNameFor($store).')');

            if (! $store->templateObject()) {
                $this->warn('  Its template is not registered — skipped.');

                continue;
            }

            $this->createDatabase($tenants, $store);

            $connection = $tenants->register($store);
            DB::purge($connection);

            $this->components->task('  schema', function () use ($connection) {
                $this->callSilent($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
                    '--database' => $connection,
                    '--path' => self::TENANT_MIGRATIONS,
                    '--realpath' => false,
                    '--force' => true,
                ]);

                return true;
            });

            if (! $this->option('no-seed')) {
                $this->components->task('  content', fn () => $tenants->forStore($store, function () use ($store) {
                    $store->templateObject()->install();

                    return true;
                }));
            }

            $this->dropCrossDatabaseForeignKeys($connection);
            $summary[] = $tenants->forStore($store, fn () => [
                $store->name,
                $tenants->databaseNameFor($store),
                count(Schema::connection($connection)->getTableListing()),
                DB::connection($connection)->table('products')->count(),
                DB::connection($connection)->table('services')->count(),
                DB::connection($connection)->table('pages')->count(),
                DB::connection($connection)->table('menu_items')->count(),
            ]);
        }

        $this->newLine();
        $this->table(['Store', 'Database', 'Tables', 'Products', 'Services', 'Pages', 'Menu items'], $summary);

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

    /**
     * Customers live in the main database. The store schema has no foreign keys pointing there,
     * but a database promoted from the old single-database layout still can.
     */
    private function dropCrossDatabaseForeignKeys(string $connection): void
    {
        $driver = DB::connection($connection)->getDriverName();
        if ($driver === 'sqlite') {
            return; // store SQLite connections run with foreign key enforcement off
        }

        foreach (config('stores.cross_database_foreign_keys', []) as $table => $columns) {
            if (! Schema::connection($connection)->hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                foreach (Schema::connection($connection)->getForeignKeys($table) as $key) {
                    if (! in_array($column, $key['columns'] ?? [], true) || ! $key['name']) {
                        continue;
                    }
                    DB::connection($connection)->statement(match ($driver) {
                        'pgsql' => 'alter table "'.$table.'" drop constraint if exists "'.$key['name'].'"',
                        default => 'alter table `'.$table.'` drop foreign key `'.$key['name'].'`',
                    });
                    $this->line('  dropped foreign key '.$table.'.'.$column);
                }
            }
        }
    }
}
