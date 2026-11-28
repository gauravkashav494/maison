<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\SyncAgent as Agent;
use App\Sync\AgentPackage;
use App\Sync\SyncAccess;
use Illuminate\Console\Command;

/**
 *   php artisan sync:agent acme                      # what the agent last reported
 *   php artisan sync:agent acme --setup-code         # what to paste when installing on the shop PC
 */
class SyncAgent extends Command
{
    protected $signature = 'sync:agent {client : The client subdomain} {--setup-code : Create a code for installing the agent} {--url= : Public base URL, if not APP_URL}';

    protected $description = 'Manage a client\'s local sync agent';

    public function handle(AgentPackage $package): int
    {
        $client = Client::where('subdomain', $this->argument('client'))->first();
        if (! $client) {
            $this->components->error('No client has that subdomain.');

            return self::FAILURE;
        }

        return $this->option('setup-code')
            ? $this->setupCode($client, $package)
            : $this->status(Agent::forClient($client));
    }

    private function setupCode(Client $client, AgentPackage $package): int
    {
        $access = app(SyncAccess::class);
        if ($problem = $access->setupCodeProblem($this->option('url'))) {
            $this->components->error($problem);

            return self::FAILURE;
        }

        $this->components->info('Setup code. It works like a password, so send it privately:');
        $this->line($access->issueSetupCode($client, $this->option('url')));
        $this->newLine();
        $this->line('Program to run on the shop PC (the 32-bit one only if the 64-bit one asks for it):');
        $this->line($package->binaryPath('amd64'));
        $this->line($package->binaryPath('386'));
        $this->newLine();
        $this->line('Setup also asks for the shop software\'s API address and key (docs/LOCAL-API.md).');

        return self::SUCCESS;
    }

    private function status(Agent $agent): int
    {
        $this->table(['', ''], [
            ['Last seen', $agent->last_seen_at?->diffForHumans() ?? 'never'],
            ['Agent version', substr((string) $agent->agent_version, 0, 12) ?: '-'],
            ['Last result', $agent->last_report['message'] ?? '-'],
            ['Counts', json_encode($agent->last_report['counts'] ?? [])],
        ]);

        foreach ($agent->last_report['errors'] ?? [] as $error) {
            $this->components->error($error);
        }

        return self::SUCCESS;
    }
}
