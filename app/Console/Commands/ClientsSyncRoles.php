<?php

namespace App\Console\Commands;

use App\Clients\ClientModules;
use App\Models\Client;
use App\Models\ClientRole;
use Illuminate\Console\Command;

/**
 * Brings the ready-made roles of existing clients (config/client_modules.php → default_roles)
 * up to date after modules are added, e.g. Brands and Taxes. New clients get them when
 * they are created (ClientRole::createDefaultsFor()); this does the same for the ones before.
 *
 * For each client and each ready-made role, by name:
 *  - missing: it is created, with the permissions the config gives it;
 *  - in the trash: left alone (the Client Admin deleted it);
 *  - there: it gains the config's permissions for modules it does not mention at all. A module
 *    the role already has some permissions for is left as it is, so access a Client Admin
 *    took away on purpose is not given back.
 *
 * Roles a Client Admin made themselves are never touched, and nothing is ever removed. Safe to
 * run again. Runs from the console, so no activity-log lines are written (no signed-in staff).
 */
class ClientsSyncRoles extends Command
{
    protected $signature = 'clients:sync-roles
        {subdomain? : Only this client}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Give the ready-made roles of existing clients the permissions of modules added since';

    public function handle(): int
    {
        $clients = Client::query()->orderBy('id')
            ->when($this->argument('subdomain'), fn ($q, $subdomain) => $q->where('subdomain', $subdomain))
            ->get();

        if ($clients->isEmpty()) {
            $this->warn($this->argument('subdomain') ? 'No client with that subdomain.' : 'No clients.');

            return $this->argument('subdomain') ? self::FAILURE : self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $defaults = ClientModules::defaultRoles();
        $changed = 0;

        foreach ($clients as $client) {
            foreach ($defaults as $name => $role) {
                $existing = ClientRole::withTrashed()->where('client_id', $client->id)->where('name', $name)->first();

                if ($existing?->trashed()) {
                    continue;
                }

                if (! $existing) {
                    $dry || ClientRole::create([
                        'client_id' => $client->id, 'name' => $name,
                        'description' => $role['description'], 'permissions' => $role['permissions'],
                    ]);
                    $this->line("{$client->name}: {$name} role created.");
                    $changed++;

                    continue;
                }

                $add = self::missing((array) $existing->permissions, $role['permissions']);
                if ($add === []) {
                    continue;
                }

                if (! $dry) {
                    $existing->permissions = array_values(array_unique([...(array) $existing->permissions, ...$add]));
                    $existing->save();
                }
                $this->line("{$client->name}: {$name} gains ".implode(', ', $add).'.');
                $changed++;
            }
        }

        $this->info(match (true) {
            $changed === 0 => 'Every ready-made role is up to date.',
            $dry => "{$changed} ".str('role')->plural($changed).' would change (dry run, nothing saved).',
            default => "{$changed} ".str('role')->plural($changed).' updated.',
        });

        return self::SUCCESS;
    }

    /**
     * The config's permissions for modules the role has none of yet.
     *
     * @param  array<string>  $current
     * @param  array<string>  $wanted
     * @return list<string>
     */
    public static function missing(array $current, array $wanted): array
    {
        $known = array_unique(array_map(fn (string $p) => strtok($p, '.'), $current));

        return array_values(array_filter($wanted, fn (string $p) => ! in_array(strtok($p, '.'), $known, true)));
    }
}
