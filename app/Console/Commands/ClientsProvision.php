<?php

namespace App\Console\Commands;

use App\Clients\ClientDatabase;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a client's database from the definition of the store that client runs.
 *
 *   php artisan clients:provision              # every client
 *   php artisan clients:provision acme         # one client
 *   php artisan clients:provision --fresh      # drop the client's tables and rebuild them
 *   php artisan clients:provision --no-seed    # schema only, no content
 *
 * For each client it creates the database (or PostgreSQL schema), builds the content schema
 * from database/migrations/client, and fills it with that store's own content by running the
 * seeders its template declares — then stamps every row with the client id. Nothing is copied
 * from another client: each database is created from the store's definition, so two clients on
 * the same store start identical and diverge as they are used.
 *
 * Re-running is safe: the schema step applies only missing migrations, the seeders update in
 * place rather than duplicate, and the stamping only fills blanks.
 */
class ClientsProvision extends Command
{
    protected $signature = 'clients:provision
        {slug? : Only this client}
        {--fresh : Drop the content tables first and rebuild them}
        {--no-seed : Create the schema without any content}
        {--copy : Move this client\'s existing rows out of the main database instead of seeding}';

    protected $description = 'Create each client database and build it from the store that client runs';

    public const CLIENT_MIGRATIONS = 'database/migrations/client';

    public function handle(ClientDatabase $databases): int
    {
        if (! $databases->isolated()) {
            $this->error('Client isolation is off. Set CLIENT_ISOLATION=database (or schema) in .env first.');

            return self::FAILURE;
        }

        $clients = $databases->clients()
            ->when($this->argument('slug'), fn ($all) => $all->where('slug', $this->argument('slug')));

        if ($clients->isEmpty()) {
            $this->error('No matching client.');

            return self::FAILURE;
        }

        $summary = [];

        foreach ($clients as $client) {
            $this->newLine();
            $this->info($client->name.'  ('.$databases->databaseNameFor($client).')');

            if (! $client->templateObject()) {
                $this->warn('  No store assigned, or its template is not registered — skipped.');

                continue;
            }

            $this->createDatabase($databases, $client);

            $connection = $databases->register($client);
            DB::purge($connection);

            $this->components->task('  schema', function () use ($connection) {
                $this->callSilent($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
                    '--database' => $connection,
                    '--path' => self::CLIENT_MIGRATIONS,
                    '--realpath' => false,
                    '--force' => true,
                ]);

                return true;
            });

            if ($this->option('copy')) {
                $this->components->task('  copy existing rows', fn () => $this->copyExistingRows($connection, $client));
            } elseif (! $this->option('no-seed')) {
                $this->components->task('  content', fn () => $databases->forClient($client, function () use ($client) {
                    $client->templateObject()->install();

                    return true;
                }));
            }

            $this->components->task('  owner', fn () => $this->stampClient($connection, $client));
            $this->dropCrossDatabaseForeignKeys($connection);

            $summary[] = $databases->forClient($client, fn () => [
                $client->name,
                $databases->databaseNameFor($client),
                count(Schema::connection($connection)->getTables($databases->databaseNameFor($client))),
                DB::connection($connection)->table('products')->count(),
                DB::connection($connection)->table('services')->count(),
                DB::connection($connection)->table('pages')->count(),
                DB::connection($connection)->table('menu_items')->count(),
            ]);
        }

        $this->newLine();
        $this->table(['Client', 'Database', 'Tables', 'Products', 'Services', 'Pages', 'Menu items'], $summary);

        return self::SUCCESS;
    }

    /**
     * Copies the rows this client already owns in the main database into its own database.
     *
     * This is the migration path for an installation that started in shared mode: the client
     * keeps the content it has been editing instead of getting a fresh seed. Rows are matched
     * on client_id, so nothing belonging to another client can travel with them, and the rows
     * in the main database are left in place (delete them only once the move is verified).
     *
     * Child tables with no client of their own (order_items, collection_product) follow their
     * parent rows.
     */
    private function copyExistingRows(string $connection, Client $client): bool
    {
        $source = DB::connection(config('database.default'));
        $target = DB::connection($connection);

        \App\Support\ForeignKeys::suspended(
            $target,
            fn () => $this->copyTables($source, $target, $connection, $client),
            fn (\Throwable $e) => $this->warn('  Could not suspend foreign keys ('.$e->getMessage().') — rows are loaded in table order.'),
        );

        return true;
    }

    private function copyTables($source, $target, string $connection, Client $client): void
    {
        foreach (config('clients.tables', []) as $table) {
            if (! Schema::hasTable($table) || ! Schema::connection($connection)->hasTable($table)) {
                continue;
            }

            $query = Schema::hasColumn($table, 'client_id')
                ? $source->table($table)->where('client_id', $client->id)
                : $this->childRows($source, $table, $client);

            if (! $query) {
                continue;
            }

            $columns = array_intersect(
                Schema::getColumnListing($table),
                Schema::connection($connection)->getColumnListing($table),
            );

            $target->table($table)->delete();

            $insert = function ($rows) use ($target, $table, $columns) {
                if ($rows->isEmpty()) {
                    return;
                }
                $target->table($table)->insert(array_map(
                    fn ($row) => array_intersect_key((array) $row, array_flip($columns)),
                    $rows->all(),
                ));
            };

            // Pivot tables have no key to chunk on; they are small enough to read at once.
            Schema::hasColumn($table, 'id')
                ? $query->orderBy('id')->chunk(500, $insert)
                : $insert($query->get());

            $this->line(sprintf('  %-22s %s rows', $table, number_format($target->table($table)->count())));
        }
    }

    /** Rows of a child table that belong to this client through their parent. */
    private function childRows($source, string $table, Client $client)
    {
        $parents = [
            'order_items' => ['orders', 'order_id'],
            'collection_product' => ['collections', 'collection_id'],
        ];

        if (! isset($parents[$table]) || ! Schema::hasColumn($table, $parents[$table][1])) {
            return null;
        }
        [$parentTable, $foreignKey] = $parents[$table];

        return $source->table($table)->whereIn(
            $foreignKey,
            $source->table($parentTable)->where('client_id', $client->id)->select('id'),
        );
    }

    /** Every row in a client database belongs to that client, including seeded content. */
    private function stampClient(string $connection, Client $client): bool
    {
        foreach (config('clients.tables', []) as $table) {
            if (! Schema::connection($connection)->hasTable($table) || ! Schema::connection($connection)->hasColumn($table, 'client_id')) {
                continue;
            }
            DB::connection($connection)->table($table)->whereNull('client_id')->update(['client_id' => $client->id]);
        }

        return true;
    }

    private function createDatabase(ClientDatabase $databases, Client $client): void
    {
        $config = $databases->connectionConfig($client);
        $name = $databases->databaseNameFor($client);

        if ($config['driver'] === 'sqlite') {
            if (! file_exists($config['database'])) {
                touch($config['database']);
                $this->line('  created '.basename($config['database']));
            }

            return;
        }

        $server = $databases->serverConnection($client);
        $connection = DB::connection($server);

        if ($databases->mode() === 'schema') {
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
     * Customers live in the main database. The client schema has no foreign keys pointing
     * there, but a database promoted from the old single-database layout still can.
     */
    private function dropCrossDatabaseForeignKeys(string $connection): void
    {
        $driver = DB::connection($connection)->getDriverName();
        if ($driver === 'sqlite') {
            return; // client SQLite connections run with foreign key enforcement off
        }

        foreach (config('clients.cross_database_foreign_keys', []) as $table => $columns) {
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
