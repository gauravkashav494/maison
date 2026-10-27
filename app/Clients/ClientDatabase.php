<?php

namespace App\Clients;

use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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
        $base = config('clients.connection') ?: config('database.default');
        $config = config("database.connections.{$base}");
        $database = $this->databaseNameFor($client);

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

    /** Every client that should have its own database. */
    public function clients(): Collection
    {
        return Client::orderBy('name')->get();
    }
}
