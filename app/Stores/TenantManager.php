<?php

namespace App\Stores;

use App\Models\Storefront;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Points the store-content models at the right database.
 *
 * With `shared` isolation nothing happens — every model keeps using the default connection
 * and the template column keeps the stores apart. With `database` or `schema` isolation the
 * models of the store being served resolve to a connection of their own, built on the fly
 * from the base connection's credentials.
 *
 * Platform data (users, storefronts, settings, store locations, sessions) always stays on
 * the default connection, so a customer account works across every store.
 */
class TenantManager
{
    private ?Storefront $store = null;

    private ?string $connection = null;

    public function mode(): string
    {
        return config('stores.isolation', 'shared');
    }

    public function isolated(): bool
    {
        return in_array($this->mode(), ['database', 'schema'], true);
    }

    /** Store currently being served, if any. */
    public function current(): ?Storefront
    {
        return $this->store;
    }

    /** Connection name store-content models should use right now (null = default). */
    public function connection(): ?string
    {
        return $this->isolated() ? $this->connection : null;
    }

    public function connectionNameFor(Storefront $store): string
    {
        return 'store_'.str_replace('-', '_', $store->slug);
    }

    public function databaseNameFor(Storefront $store): string
    {
        return config('stores.prefix', 'store_').str_replace('-', '_', $store->slug);
    }

    /** Serve this store: register (once) and select its connection. */
    public function use(?Storefront $store): void
    {
        $this->store = $store;
        $this->connection = $store && $this->isolated() ? $this->register($store) : null;
    }

    public function forget(): void
    {
        $this->use(null);
    }

    /** Run a callback with another store selected, then restore the previous one. */
    public function forStore(Storefront $store, callable $callback): mixed
    {
        $previous = $this->store;
        $this->use($store);

        try {
            return $callback($store);
        } finally {
            $this->use($previous);
        }
    }

    /** Define the store's connection in config (idempotent) and return its name. */
    public function register(Storefront $store): string
    {
        $name = $this->connectionNameFor($store);
        if (Config::has("database.connections.{$name}")) {
            return $name;
        }

        Config::set("database.connections.{$name}", $this->connectionConfig($store));

        return $name;
    }

    /** Connection settings for a store, cloned from the base connection. */
    public function connectionConfig(Storefront $store, bool $withoutDatabase = false): array
    {
        $base = config('stores.connection') ?: config('database.default');
        $config = config("database.connections.{$base}");
        $database = $this->databaseNameFor($store);

        if ($this->mode() === 'schema' && ($config['driver'] ?? null) === 'pgsql') {
            $config['search_path'] = $withoutDatabase ? 'public' : $database.',public';

            return $config;
        }

        if (($config['driver'] ?? null) === 'sqlite') {
            // Local development / tests: one file per store next to the main database. Foreign keys
            // are left off because customers and stores live in different files, exactly like the
            // cross-database keys dropped on MySQL/PostgreSQL.
            $config['database'] = $withoutDatabase ? ':memory:' : database_path($database.'.sqlite');
            $config['foreign_key_constraints'] = false;

            return $config;
        }

        // database mode on MySQL/PostgreSQL: same server, its own database.
        $config['database'] = $withoutDatabase ? ($config['driver'] === 'pgsql' ? 'postgres' : null) : $database;

        return $config;
    }

    /** A connection to the server itself, used to CREATE DATABASE / CREATE SCHEMA. */
    public function serverConnection(Storefront $store): string
    {
        $name = $this->connectionNameFor($store).'_server';
        Config::set("database.connections.{$name}", $this->connectionConfig($store, withoutDatabase: true));
        DB::purge($name);

        return $name;
    }

    /** Every store that should have its own database. */
    public function stores(): \Illuminate\Support\Collection
    {
        return Storefront::orderBy('name')->get();
    }
}
