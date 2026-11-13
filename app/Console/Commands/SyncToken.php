<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\SyncToken as Token;
use Illuminate\Console\Command;

/**
 *   php artisan sync:token acme                 # issue a token for acme's agent
 *   php artisan sync:token acme --name=shop-pc  # label it
 *   php artisan sync:token acme --revoke        # revoke all of acme's tokens
 */
class SyncToken extends Command
{
    protected $signature = 'sync:token {client : The client subdomain} {--name=local-agent : A label for this token} {--revoke : Revoke the client\'s tokens}';

    protected $description = 'Issue or revoke the token a client\'s local sync agent uses';

    public function handle(): int
    {
        $client = Client::where('subdomain', $this->argument('client'))->first();
        if (! $client) {
            $this->components->error('No client has that subdomain.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $count = Token::active()->where('client_id', $client->id)->update(['revoked_at' => now()]);
            $this->components->info("Revoked {$count} token(s) for {$client->name}.");

            return self::SUCCESS;
        }

        [, $plain] = Token::issue($client, (string) $this->option('name'));
        $this->components->info("Token for {$client->name}. It is shown only once:");
        $this->line($plain);

        return self::SUCCESS;
    }
}
