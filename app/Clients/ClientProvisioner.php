<?php

namespace App\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds a client's own database from the admin, by running `clients:provision <slug>`.
 *
 * Used right after a client is created, and by the "Provision database" action to retry. The
 * database is seeded only when it is first created; a retry on an existing database just applies
 * missing tables, so a client's edited content is never overwritten. In shared mode there is nothing to build and this does nothing.
 */
class ClientProvisioner
{
    public function __construct(private readonly ClientDatabase $databases) {}

    public function isNeeded(): bool
    {
        return $this->databases->isolated();
    }

    /** @return array{ok: bool, message: string} */
    public function provision(Client $client, bool $withSamples = false): array
    {
        if (! $this->isNeeded()) {
            return ['ok' => true, 'message' => 'Shared database — nothing to create.'];
        }

        // Seeding a store takes ~25s, close to a web request's 30s limit; and closing the tab
        // must not leave a half-built database behind.
        @set_time_limit(300);
        ignore_user_abort(true);

        try {
            // Seed only a brand-new database: re-seeding updates rows in place, which would
            // overwrite content the client has already edited.
            $code = Artisan::call('clients:provision', array_filter([
                'slug' => $client->slug,
                '--no-seed' => $this->databaseExists($client) ?: null,
                '--no-samples' => $withSamples ? null : true,
            ]));
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            Log::error('Client provisioning failed', ['client' => $client->slug, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if ($code !== 0 || str_contains($output, 'skipped')) {
            Log::warning('Client provisioning did not complete', ['client' => $client->slug, 'output' => $output]);

            return ['ok' => false, 'message' => $output !== '' ? $output : 'The provision command reported a failure.'];
        }

        // The create form has already measured this client as empty; the seeded content counts.
        ClientQuota::for($client)->forget();

        return ['ok' => true, 'message' => 'Database '.$this->databases->databaseNameFor($client).' is ready.'];
    }

    public function databaseExists(Client $client): bool
    {
        $config = $this->databases->connectionConfig($client);
        $name = $this->databases->databaseNameFor($client);

        if ($config['driver'] === 'sqlite') {
            return file_exists($config['database']);
        }

        $server = DB::connection($this->databases->serverConnection($client));

        return (bool) match (true) {
            $this->databases->mode() === 'schema' => $server->selectOne('select 1 from information_schema.schemata where schema_name = ?', [$name]),
            $config['driver'] === 'pgsql' => $server->selectOne('select 1 from pg_database where datname = ?', [$name]),
            default => $server->selectOne('select 1 from information_schema.schemata where schema_name = ?', [$name]),
        };
    }
}
