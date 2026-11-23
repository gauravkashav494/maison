<?php

namespace App\Clients;

use App\Models\Client;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Points the content models at the right client's database.
 *
 * With `shared` isolation nothing is switched — every model keeps the default connection and
 * the `client_id` column keeps the clients apart. With `database` or `schema` isolation the
 * content models of the client being served resolve to a connection of their own, built from
 * the base connection's credentials; the client never supplies a host, name or password, so a
 * request cannot point the application at a database of its choosing.
 *
 * Platform data (super admins, clients, their staff, customers, addresses, storefronts,
 * settings) always stays on the default connection.
 *
 * Connections do not leak between requests: `use()` is called once per request from
 * ResolveClient / ApplyClientScope before anything queries, and `forget()` restores the
 * default. Queue jobs and console commands select a client explicitly through `forClient()`,
 * which always restores the previous selection, even when the callback throws.
 */
class ClientDatabase
{
    private ?Client $client = null;

    private ?string $connection = null;

    public function mode(): string
    {
        return config('clients.isolation', 'shared');
    }

    public function isolated(): bool
    {
        return in_array($this->mode(), ['database', 'schema'], true);
    }

    /** Client currently selected, if any. */
    public function current(): ?Client
    {
        return $this->client;
    }

    /** Connection name content models should use right now (null = default). */
    public function connection(): ?string
    {
        return $this->isolated() ? $this->connection : null;
    }

    public function connectionNameFor(Client $client): string
    {
        return 'client_'.$client->key();
    }

    public function databaseNameFor(Client $client): string
    {
        return $client->databaseName();
    }

    /** Names the database server keeps for itself; a client must never be pointed at one. */
    private const RESERVED_NAMES = ['mysql', 'information_schema', 'performance_schema', 'sys', 'postgres', 'template0', 'template1', 'public'];

    /**
     * Why a client cannot use this database name, or null when it can.
     *
     * A database belongs to exactly one client: two clients on one database would read and
     * overwrite each other's catalogue, orders and settings. So the name is checked against what
     * every other client resolves to — an explicit name and the generated client_<subdomain> one
     * alike, deleted clients and theme previews included (their database is kept) — compared
     * without case, since MySQL on Windows/macOS and table-name settings may ignore it. The
     * platform database and the server's own are off limits, and the name must be a plain
     * identifier because it goes into CREATE DATABASE and, on SQLite, a file path.
     */
    public function problemWithName(string $name, ?Client $ignore = null): ?string
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name)) {
            return 'Use letters, numbers and underscores only, starting with a letter (at most 64 characters).';
        }

        $lower = strtolower($name);

        if (in_array($lower, self::RESERVED_NAMES, true) || str_starts_with($lower, 'pg_') || $lower === strtolower($this->platformDatabaseName())) {
            return '"'.$name.'" is a system or platform database and cannot be given to a client.';
        }

        $owner = Client::withoutGlobalScopes()->withTrashed()
            ->when($ignore?->exists, fn ($q) => $q->whereKeyNot($ignore->getKey()))
            ->get(['id', 'name', 'subdomain', 'database', 'is_theme', 'deleted_at'])
            ->first(fn (Client $other) => strtolower($other->databaseName()) === $lower);

        if ($owner) {
            return 'The database "'.$name.'" already belongs to '.($owner->is_theme ? 'a theme preview' : 'the client "'.$owner->name.'"')
                .($owner->trashed() ? ' (deleted, its data is kept)' : '').'. Enter a different database name.';
        }

        return null;
    }

    /** The platform's own database name (for SQLite, the file name without .sqlite). */
    private function platformDatabaseName(): string
    {
        $base = config('clients.connection') ?: config('database.default');
        $database = (string) config("database.connections.{$base}.database");

        return config("database.connections.{$base}.driver") === 'sqlite'
            ? pathinfo($database, PATHINFO_FILENAME)
            : $database;
    }

    /** Cache key for data read from the selected client's content, so clients never share it. */
    public function cacheKey(string $key): string
    {
        return $this->client ? 'client:'.$this->client->id.':'.$key : $key;
    }

    /** Serve this client: register (once) and select its connection. */
    public function use(?Client $client): void
    {
        $this->client = $client;
        $this->connection = $client && $this->isolated() ? $this->register($client) : null;
    }

    public function forget(): void
    {
        $this->use(null);
    }

    /** Run a callback with another client selected, then restore the previous one. */
    public function forClient(Client $client, callable $callback): mixed
    {
        $previous = $this->client;
        $this->use($client);

        try {
            return $callback($client);
        } finally {
            $this->use($previous);
        }
    }

    /**
     * forClient() for reads that describe a client rather than serve it: a client whose database
     * is not built yet (just created, still being set up, or provisioning failed) gets $default instead of an error,
     * so the admin pages that list it, and offer the retry, keep working.
     */
    public function forClientOr(Client $client, callable $callback, mixed $default): mixed
    {
        // Being built right now (ClientSetup): its tables are half-migrated, so do not read them yet.
        if (! ClientSetup::isBuildingHere($client) && (ClientSetup::status($client)['state'] ?? null) === ClientSetup::RUNNING) {
            return $default;
        }

        try {
            return $this->forClient($client, $callback);
        } catch (QueryException $e) {
            if (! $this->isNotProvisioned($e)) {
                throw $e;
            }

            return $default;
        }
    }

    /** Did this query fail because the client's database, or its tables, do not exist yet? */
    private function isNotProvisioned(QueryException $e): bool
    {
        return $e->getPrevious() instanceof SQLiteDatabaseDoesNotExistException
            || Str::contains($e->getMessage(), [
                '[1049]',           // MySQL: unknown database
                'SQLSTATE[42S02]',  // MySQL: table not found
                'SQLSTATE[42P01]',  // PostgreSQL: relation not found
                'SQLSTATE[3F000]',  // PostgreSQL: schema not found
                'SQLSTATE[3D000]',  // PostgreSQL: database not found
                'does not exist (Connection:', // PostgreSQL: refused at connect time
                'no such table',    // SQLite
            ]);
    }

    /** Define the client's connection in config (idempotent) and return its name. */
    public function register(Client $client): string
    {
        $name = $this->connectionNameFor($client);
        if (Config::has("database.connections.{$name}")) {
            return $name;
        }

        Config::set("database.connections.{$name}", $this->connectionConfig($client));

        return $name;
    }

    /** Connection settings for a client, cloned from the base connection. */
    public function connectionConfig(Client $client, bool $withoutDatabase = false): array
    {
        return $this->configFor($this->databaseNameFor($client), $withoutDatabase);
    }

    /** Connection settings for a database (or schema) of this name on the base connection's server. */
    private function configFor(string $database, bool $withoutDatabase = false): array
    {
        $base = config('clients.connection') ?: config('database.default');
        $config = config("database.connections.{$base}");

        if ($this->mode() === 'schema' && ($config['driver'] ?? null) === 'pgsql') {
            $config['search_path'] = $withoutDatabase ? 'public' : $database.',public';

            return $config;
        }

        if (($config['driver'] ?? null) === 'sqlite') {
            // Local development and tests: one file per client next to the main database. Foreign
            // keys are off because customers and clients live in another file, exactly like the
            // cross-database keys that MySQL and PostgreSQL cannot have either.
            $config['database'] = $withoutDatabase ? ':memory:' : database_path($database.'.sqlite');
            $config['foreign_key_constraints'] = false;

            return $config;
        }

        // database mode on MySQL/PostgreSQL: same server, its own database.
        $config['database'] = $withoutDatabase ? ($config['driver'] === 'pgsql' ? 'postgres' : null) : $database;

        return $config;
    }

    /** A connection to the server itself, used to CREATE DATABASE / CREATE SCHEMA. */
    public function serverConnection(Client $client): string
    {
        $name = $this->connectionNameFor($client).'_server';
        Config::set("database.connections.{$name}", $this->connectionConfig($client, withoutDatabase: true));
        DB::purge($name);

        return $name;
    }

    /** Does a client database (or PostgreSQL schema) of this name exist on the server? */
    public function exists(string $database): bool
    {
        $config = $this->configFor($database);

        if ($config['driver'] === 'sqlite') {
            return file_exists($config['database']);
        }

        $server = DB::connection($this->serverConnectionFor($database));

        return (bool) match (true) {
            $this->mode() === 'schema', $config['driver'] !== 'pgsql' => $server->selectOne('select 1 from information_schema.schemata where schema_name = ?', [$database]),
            default => $server->selectOne('select 1 from pg_database where datname = ?', [$database]),
        };
    }

    /**
     * A client's database name changed (typed in, or generated from a new subdomain): move its
     * database to the new name, data and all, and return true. Nothing is copied or re-saved —
     * the tables themselves move, so every row, id, auto-increment counter and timestamp stays
     * exactly as it was.
     *
     * Returns false, doing nothing, when there is nothing to move (the old database was never
     * built) or the new name already exists (the client is being pointed at a database that is
     * already there). Throws, leaving the old database untouched, when the move cannot be done.
     *
     *   MySQL       no RENAME DATABASE exists: the new database is created and every table moved
     *               into it in one RENAME TABLE (instant, same server), then the empty old one dropped.
     *   PostgreSQL  ALTER DATABASE … RENAME (refused while anyone is connected), or ALTER SCHEMA in schema mode.
     *   SQLite      the file is renamed.
     */
    public function rename(Client $client, string $from, string $to, ?string $fromConnection = null): bool
    {
        if (! $this->isolated() || strcasecmp($from, $to) === 0 || ! $this->exists($from) || $this->exists($to)) {
            return false;
        }

        // Nothing may hold the old database open while it moves, and nothing may keep using it after.
        $this->forgetConnection($fromConnection ?? $this->connectionNameFor($client));
        $this->forgetConnection($this->connectionNameFor($client));

        $config = $this->configFor($from);
        $driver = $config['driver'];

        if ($driver === 'sqlite') {
            $target = $this->configFor($to)['database'];
            foreach (['', '-wal', '-shm', '-journal'] as $suffix) {
                if (file_exists($config['database'].$suffix)) {
                    rename($config['database'].$suffix, $target.$suffix);
                }
            }
        } else {
            $server = DB::connection($this->serverConnectionFor($from));

            match (true) {
                $this->mode() === 'schema' => $server->statement('alter schema '.$this->quote($from, $driver).' rename to '.$this->quote($to, $driver)),
                $driver === 'pgsql' => $server->statement('alter database '.$this->quote($from, $driver).' rename to '.$this->quote($to, $driver)),
                default => $this->moveMysqlTables($server, $from, $to),
            };
        }

        $this->forgetConnection($this->connectionNameFor($client));
        if ($this->client?->is($client)) {
            $this->use($client);
        }

        return true;
    }

    /** MySQL: create the new database and move every table into it in one statement. */
    private function moveMysqlTables(Connection $server, string $from, string $to): void
    {
        $count = fn (string $sql) => (int) ($server->selectOne($sql, [$from])->n ?? 0);

        // RENAME TABLE cannot carry these across databases; refuse rather than lose them.
        if ($count('select count(*) as n from information_schema.triggers where trigger_schema = ?')
            || $count("select count(*) as n from information_schema.tables where table_schema = ? and table_type <> 'BASE TABLE'")) {
            throw new RuntimeException('The database "'.$from.'" has views or triggers, which cannot be moved automatically. Rename it on the database server, then enter the new name here.');
        }

        $charset = $server->selectOne('select default_character_set_name as charset, default_collation_name as collation from information_schema.schemata where schema_name = ?', [$from]);
        $tables = array_map(fn ($row) => $row->name, $server->select("select table_name as name from information_schema.tables where table_schema = ? and table_type = 'BASE TABLE'", [$from]));

        $server->statement('create database '.$this->quote($to, 'mysql').' character set '.($charset->charset ?? 'utf8mb4').' collate '.($charset->collation ?? 'utf8mb4_unicode_ci'));

        try {
            if ($tables !== []) {
                // One statement: either every table moves or none does.
                $server->statement('rename table '.implode(', ', array_map(
                    fn (string $table) => $this->quote($from, 'mysql').'.'.$this->quote($table, 'mysql').' to '.$this->quote($to, 'mysql').'.'.$this->quote($table, 'mysql'),
                    $tables,
                )));
            }
        } catch (Throwable $e) {
            $server->statement('drop database if exists '.$this->quote($to, 'mysql'));

            throw $e;
        }

        // Drop the old one only when nothing at all is left in it (stored routines, events).
        if (! $count('select count(*) as n from information_schema.tables where table_schema = ?')
            && ! $count('select count(*) as n from information_schema.routines where routine_schema = ?')
            && ! $count('select count(*) as n from information_schema.events where event_schema = ?')) {
            $server->statement('drop database '.$this->quote($from, 'mysql'));
        }
    }

    private function quote(string $identifier, string $driver): string
    {
        return $driver === 'pgsql'
            ? '"'.str_replace('"', '""', $identifier).'"'
            : '`'.str_replace('`', '``', $identifier).'`';
    }

    /** A server connection not tied to any one client, for checks and moves by name. */
    private function serverConnectionFor(string $database): string
    {
        $name = 'clients_server';
        Config::set("database.connections.{$name}", $this->configFor($database, withoutDatabase: true));
        DB::purge($name);

        return $name;
    }

    /** Close a client connection and drop its settings, so the next use() builds it afresh. */
    private function forgetConnection(string $name): void
    {
        DB::purge($name);
        $connections = config('database.connections');
        unset($connections[$name]);
        Config::set('database.connections', $connections);
    }

    /** Every client that should have its own database, theme previews included. */
    public function clients(): Collection
    {
        return Client::withThemes()->orderBy('name')->get();
    }
}
