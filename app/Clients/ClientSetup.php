<?php

namespace App\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds a new client's database in the background after "Create client", so the super admin
 * is not kept waiting (migrations + store content take tens of seconds). The client's view
 * page reads status() and refreshes itself until the database is ready.
 */
class ClientSetup
{
    public const RUNNING = 'running';

    public const FAILED = 'failed';

    /** Id of the client this process is building (its own reads must not be short-circuited). */
    private static ?int $building = null;

    public function __construct(private readonly ClientProvisioner $provisioner) {}

    public static function isBuildingHere(Client $client): bool
    {
        return self::$building === (int) $client->id;
    }

    /**
     * Start building the database in a separate background process (artisan clients:setup), so
     * the create request answers straight away. A separate process works under any server
     * setup (mod_php keeps a request open until its own work ends, and PHP's defer() is not
     * safe here: Swoole defines a global defer() of its own).
     */
    public function startInBackground(Client $client, bool $withSamples): void
    {
        if (! $this->provisioner->isNeeded()) {
            return;
        }

        // Tests run it in-process: a detached process would boot with the real .env and database.
        if (app()->runningUnitTests()) {
            $this->run($client, $withSamples);

            return;
        }

        Cache::put(self::key($client), ['state' => self::RUNNING, 'started_at' => now()->toIso8601String()], now()->addHour());

        $command = implode(' ', array_filter([
            escapeshellarg(PHP_BINDIR.'/php'),
            escapeshellarg(base_path('artisan')),
            'clients:setup',
            (int) $client->id,
            $withSamples ? '--samples' : null,
        ]));
        // setsid + nohup + & : fully detached from the web request. Every stream is redirected, so
        // exec() returns at once; the outcome goes to status() and failures to the Laravel log.
        exec('setsid nohup '.$command.' > /dev/null 2>&1 < /dev/null &');
    }

    /** Build the database now (used by clients:setup) and record the outcome for the client page. */
    public function run(Client $client, bool $withSamples): array
    {
        Cache::put(self::key($client), ['state' => self::RUNNING, 'started_at' => now()->toIso8601String()], now()->addHour());

        self::$building = (int) $client->id;
        try {
            $result = $this->provisioner->provision($client, $withSamples);
        } catch (Throwable $e) {
            Log::error('Client setup failed', ['client' => $client->subdomain, 'error' => $e->getMessage()]);
            $result = ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            self::$building = null;
        }

        $result['ok']
            ? Cache::forget(self::key($client))
            : Cache::put(self::key($client), ['state' => self::FAILED, 'message' => (string) $result['message']], now()->addDay());

        return $result;
    }

    /** @return array{state: string, message?: string, started_at?: string}|null null once the database is ready */
    public static function status(Client $client): ?array
    {
        $status = Cache::get(self::key($client));

        // A setup that never reported back (server restarted mid-way) is shown as failed, not "running" forever.
        if (($status['state'] ?? null) === self::RUNNING && isset($status['started_at']) && now()->diffInMinutes($status['started_at'], true) > 10) {
            return ['state' => self::FAILED, 'message' => 'The setup did not finish.'];
        }

        return $status;
    }

    public static function isRunning(Client $client): bool
    {
        return (self::status($client)['state'] ?? null) === self::RUNNING;
    }

    public static function forget(Client $client): void
    {
        Cache::forget(self::key($client));
    }

    private static function key(Client $client): string
    {
        return 'client-setup:'.$client->id;
    }
}
