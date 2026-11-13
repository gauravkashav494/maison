<?php

namespace App\Console\Commands;

use App\Clients\ClientSetup;
use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Builds one new client's database and records the outcome for its view page. Started in the
 * background by ClientSetup::startInBackground right after "Create client"; can also be run by hand.
 */
class ClientsSetup extends Command
{
    protected $signature = 'clients:setup {client : Client id} {--samples : Add the store\'s sample products and services}';

    protected $description = 'Create and fill a new client\'s database, reporting progress to its client page';

    public function handle(ClientSetup $setup): int
    {
        $client = Client::find($this->argument('client'));
        if (! $client) {
            $this->error('No such client.');

            return self::FAILURE;
        }

        $result = $setup->run($client, (bool) $this->option('samples'));
        $this->line($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
