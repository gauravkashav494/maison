<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\SyncAgent as Agent;
use App\Models\SyncToken;
use App\Sync\AgentPackage;
use Illuminate\Console\Command;

/**
 *   php artisan sync:agent acme                      # what the agent last reported
 *   php artisan sync:agent acme --setup-code         # what to paste when installing on the shop PC
 *   php artisan sync:agent acme --schema             # the local tables the agent found
 *   php artisan sync:agent acme --map=acme.json      # set which tables and columns to sync
 */
class SyncAgent extends Command
{
    private const SECTIONS = ['products', 'stock', 'customers', 'order_status', 'orders_in', 'order_items_in', 'stock_on_import', 'customers_in', 'products_in'];

    protected $signature = 'sync:agent {client : The client slug} {--setup-code : Create a code for installing the agent} {--url= : Public base URL, if not APP_URL} {--schema : Show the local tables} {--map= : JSON mapping file to apply}';

    protected $description = 'Manage a client\'s local sync agent';

    public function handle(AgentPackage $package): int
    {
        $client = Client::where('slug', $this->argument('client'))->first();
        if (! $client) {
            $this->components->error('No client has that slug.');

            return self::FAILURE;
        }
        $agent = Agent::forClient($client);

        return match (true) {
            (bool) $this->option('setup-code') => $this->setupCode($client, $package),
            (bool) $this->option('schema') => $this->schema($agent),
            filled($this->option('map')) => $this->map($agent, (string) $this->option('map')),
            default => $this->status($agent),
        };
    }

    private function setupCode(Client $client, AgentPackage $package): int
    {
        $url = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        if (! str_starts_with($url, 'https://')) {
            $this->components->error('The agent sends its token to this address, so it must be https://.');

            return self::FAILURE;
        }

        if (! $package->isSigned('amd64') || ! $package->isSigned('386')) {
            $this->components->error('The agent is not built and signed yet. See docs/SYNC.md.');

            return self::FAILURE;
        }

        [, $token] = SyncToken::issue($client, 'agent');

        $this->components->info('Setup code. It works like a password, so send it privately:');
        $this->line(AgentPackage::setupCode($url.'/api/sync/v1', $token));
        $this->newLine();
        $this->line('Program to run on the shop PC (the 32-bit one only if the 64-bit one asks for it):');
        $this->line($package->binaryPath('amd64'));
        $this->line($package->binaryPath('386'));

        return self::SUCCESS;
    }

    private function schema(Agent $agent): int
    {
        if (! $agent->schema_snapshot) {
            $this->components->warn('The agent has not reported any tables yet.');

            return self::SUCCESS;
        }

        foreach ($agent->schema_snapshot as $table => $columns) {
            $this->line("<info>{$table}</info>: ".collect($columns)->map(fn ($column) => "{$column['name']} ({$column['type']})")->implode(', '));
        }

        return self::SUCCESS;
    }

    private function map(Agent $agent, string $file): int
    {
        $mapping = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($mapping)) {
            $this->components->error('That file is missing or is not valid JSON.');

            return self::FAILURE;
        }

        $unknown = array_diff(array_keys($mapping), self::SECTIONS);
        if ($unknown) {
            $this->components->error('Unknown sections: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $problems = $this->problems($mapping, (array) $agent->schema_snapshot);
        if ($problems) {
            foreach ($problems as $problem) {
                $this->components->error($problem);
            }

            return self::FAILURE;
        }

        $agent->update(['mapping' => $mapping]);
        $this->components->info('Mapping saved. The agent uses it on its next cycle.');

        return self::SUCCESS;
    }

    /** Names that do not exist locally. Skipped until the agent has reported its tables. */
    private function problems(array $mapping, array $schema): array
    {
        if (! $schema) {
            return [];
        }

        $problems = [];
        foreach ($mapping as $name => $section) {
            $table = $section['table'] ?? null;
            if (! isset($schema[$table])) {
                $problems[] = "{$name}: the local database has no table named ".($table ?? '(none)').'.';

                continue;
            }

            $known = array_map('strtolower', array_column($schema[$table], 'name'));
            $used = [...array_values($section['columns'] ?? []), ...array_keys($section['defaults'] ?? [])];
            foreach (['key', 'parent', 'sku_column', 'qty_column'] as $field) {
                if (filled($section[$field] ?? null)) {
                    $used[] = $section[$field];
                }
            }

            foreach (array_diff(array_map('strtolower', $used), $known) as $missing) {
                $problems[] = "{$name}: {$table} has no column named {$missing}.";
            }
        }

        return $problems;
    }

    private function status(Agent $agent): int
    {
        $this->table(['', ''], [
            ['Last seen', $agent->last_seen_at?->diffForHumans() ?? 'never'],
            ['Agent version', substr((string) $agent->agent_version, 0, 12) ?: '-'],
            ['Mapping', $agent->mapping ? implode(', ', array_keys($agent->mapping)) : 'not set'],
            ['Tables found', count((array) $agent->schema_snapshot)],
            ['Last result', json_encode($agent->last_report['counts'] ?? [])],
        ]);

        foreach ($agent->last_report['errors'] ?? [] as $error) {
            $this->components->error($error);
        }

        return self::SUCCESS;
    }
}
