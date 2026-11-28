<?php

namespace App\Sync;

use App\Models\Client;
use App\Models\SyncAgent;
use App\Models\SyncToken;
use App\Support\Activity\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * A client's access for its shop's sync agent: the setup code that installs it, and revoking it.
 * Used by the client page in the admin panel and by the sync:agent / sync:token commands, and
 * logged when a signed-in panel account does it.
 */
class SyncAccess
{
    public function __construct(private readonly AgentPackage $package) {}

    /** Why no setup code can be made right now, or null when one can. */
    public function setupCodeProblem(?string $baseUrl = null): ?string
    {
        if (! str_starts_with($this->baseUrl($baseUrl), 'https://')) {
            return 'The agent sends its token to this server, so the site address (APP_URL) must start with https://.';
        }
        if (! $this->package->isSigned('amd64') || ! $this->package->isSigned('386')) {
            return 'The agent is not built and signed yet. See docs/SYNC.md.';
        }

        return null;
    }

    /**
     * A new setup code for the client's agent: the server address and a new token. Shown once:
     * only the token's hash is kept.
     *
     * @throws RuntimeException when setupCodeProblem() names a reason
     */
    public function issueSetupCode(Client $client, ?string $baseUrl = null): string
    {
        if ($problem = $this->setupCodeProblem($baseUrl)) {
            throw new RuntimeException($problem);
        }

        [, $token] = SyncToken::issue($client, 'agent');

        $log = app(ActivityLog::class);
        $log->record('created', 'created a shop software setup code for '.$log->describer()->reference($client), $client);

        return AgentPackage::setupCode($this->baseUrl($baseUrl).'/api/sync/v1', $token);
    }

    /**
     * Where the client's agent finds the shop's billing software. The agent reads this every
     * cycle (GET agent/config), so a change here reaches the shop PC with no reinstall.
     *
     * @return array{url: string, key: ?string}
     */
    public function connection(Client $client): array
    {
        $agent = SyncAgent::query()->where('client_id', $client->id)->first();

        return ['url' => $agent?->local_api_url ?: SyncAgent::DEFAULT_LOCAL_API_URL, 'key' => $agent?->local_api_key ?: null];
    }

    /** Saves the billing software's API address, and its key unless $key is blank (kept as it is). */
    public function saveConnection(Client $client, string $url, ?string $key): void
    {
        $agent = SyncAgent::forClient($client);
        $before = $this->connection($client);
        $url = rtrim(trim($url), '/');
        $key = filled($key) ? trim($key) : null;

        $changes = [];
        if ($url !== $before['url']) {
            $changes[] = ['field' => 'local_api_url', 'label' => 'API address', 'old' => $before['url'], 'new' => $url];
        }
        if ($key !== null && $key !== $before['key']) {
            // Named, never kept: secrets are not stored in the log.
            $changes[] = ['field' => 'local_api_key', 'label' => 'API key', 'old' => null, 'new' => null, 'secret' => true];
        }
        if ($changes === []) {
            return;
        }

        $agent->update(['local_api_url' => $url] + ($key !== null ? ['local_api_key' => $key] : []));

        $log = app(ActivityLog::class);
        $log->record(
            'updated',
            'changed the billing software connection of '.$log->describer()->reference($client).': '.implode(' and ', array_column($changes, 'label')),
            $client,
            $changes,
        );
    }

    /** Revokes every token the client's agents use; returns how many. */
    public function revoke(Client $client): int
    {
        $count = SyncToken::active()->where('client_id', $client->id)->update(['revoked_at' => now()]);

        if ($count > 0) {
            $log = app(ActivityLog::class);
            $log->record('revoked', 'revoked the shop software sync access of '.$log->describer()->reference($client), $client);
        }

        return $count;
    }

    /** Uses the count a list loaded as active_sync_tokens (SyncAccess::withStatus()) when there is one. */
    public function activeTokens(Client $client): int
    {
        return isset($client->active_sync_tokens)
            ? (int) $client->active_sync_tokens
            : SyncToken::active()->where('client_id', $client->id)->count();
    }

    /**
     * Loads what status() reads for a whole list of clients at once, so a long list does not
     * ask the database twice for every row.
     */
    public static function withStatus(Builder $clients): Builder
    {
        return $clients->with('syncAgent')
            ->withCount(['syncTokens as active_sync_tokens' => fn (Builder $tokens) => $tokens->whereNull('revoked_at')]);
    }

    /**
     * The setup .exe for the shop PC's Windows ($arch: amd64 or 386), logged as downloaded for
     * the client it is meant for. Only with its build signed: the installer is built after signing.
     */
    public function agentDownload(Client $client, string $arch): string
    {
        if (! in_array($arch, AgentPackage::ARCHITECTURES, true) || ! $this->package->isSigned($arch)) {
            throw new RuntimeException('The agent is not built and signed yet. See docs/SYNC.md.');
        }
        if (! $this->package->hasInstaller($arch)) {
            throw new RuntimeException('The installer is not built yet. See docs/SYNC.md.');
        }

        $log = app(ActivityLog::class);
        $log->record('downloaded', 'downloaded the sync agent installer for '.self::windows($arch).' for '.$log->describer()->reference($client), $client);

        return $this->package->installerPath($arch);
    }

    public static function windows(string $arch): string
    {
        return (str_starts_with($arch, '386') ? '32-bit' : '64-bit').(str_ends_with($arch, '-win7') ? ' Windows 7 / 8.1' : ' Windows 10 / 11');
    }

    /**
     * Where the client's agent stands, for the client page:
     * not set up, waiting for its first sync, syncing, a problem, not seen lately, or revoked.
     *
     * @return array{state: string, label: string, color: string, agent: ?SyncAgent}
     */
    public function status(Client $client): array
    {
        $agent = $client->relationLoaded('syncAgent')
            ? $client->syncAgent
            : SyncAgent::query()->where('client_id', $client->id)->first();
        $active = $this->activeTokens($client) > 0;
        $seen = $agent?->last_seen_at;

        [$state, $label, $color] = match (true) {
            ! $active && ! $seen => ['none', 'Not set up', 'gray'],
            ! $active => ['revoked', 'Access revoked', 'gray'],
            ! $seen => ['waiting', 'Waiting for the agent', 'info'],
            ($agent->last_report['ok'] ?? false) !== true => ['problem', 'Problem', 'danger'],
            // It syncs every 5 minutes: three missed cycles is worth a look.
            $seen->lt(now()->subMinutes(15)) => ['quiet', 'Not seen lately', 'warning'],
            default => ['ok', 'Syncing', 'success'],
        };

        return ['state' => $state, 'label' => $label, 'color' => $color, 'agent' => $agent];
    }

    private function baseUrl(?string $baseUrl): string
    {
        return rtrim((string) ($baseUrl ?: config('app.url')), '/');
    }
}
