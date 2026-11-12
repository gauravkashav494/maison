<?php

namespace App\Console\Commands;

use App\Sync\AgentPackage;
use Illuminate\Console\Command;
use RuntimeException;

/**
 *   php artisan sync:agent-sign ~/ecomvision-agent.pem --generate   # first time, before building
 *   php artisan sync:agent-sign ~/ecomvision-agent.pem              # after agent/build.sh
 */
class SyncAgentSign extends Command
{
    protected $signature = 'sync:agent-sign {key : Path to the private key, outside the project} {--generate : Create the key first}';

    protected $description = 'Sign the agent builds so that installed agents will accept them';

    public function handle(AgentPackage $package): int
    {
        $path = (string) $this->argument('key');

        if (str_starts_with((string) realpath(dirname($path)), base_path())) {
            $this->components->error('Keep the private key outside the project, so it never reaches the web server or git.');

            return self::FAILURE;
        }

        try {
            if ($this->option('generate')) {
                $package->generateKey($path);
                $this->components->warn('Back this key up. Without it, installed agents can never be updated.');
            }
            $signed = $package->sign($path);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $signed) {
            if ($this->option('generate')) {
                $this->components->info('Key created. Now run agent/build.sh, then sign again without --generate.');

                return self::SUCCESS;
            }
            $this->components->error('No builds found. Run agent/build.sh first.');

            return self::FAILURE;
        }

        $this->components->info('Signed: '.implode(', ', $signed).'. Deploy resources/agent/dist and agents update on their next cycle.');

        return self::SUCCESS;
    }
}
