<?php

namespace App\Console\Commands;

use App\Clients\ClientDatabase;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Support\GuestCustomer;
use Illuminate\Console\Command;

/**
 * Gives the guest orders and bookings placed before checkout began creating customers the
 * same treatment: each is linked to the store's customer for its email, created as a guest
 * (no password) when there is none. Safe to run again; linked rows are left alone.
 */
class CustomersLinkGuests extends Command
{
    protected $signature = 'customers:link-guests {subdomain? : Only this client}';

    protected $description = 'Link guest orders and bookings to a customer record for their email';

    public function handle(ClientDatabase $databases): int
    {
        $clients = $databases->clients()
            ->when($this->argument('subdomain'), fn ($all) => $all->where('subdomain', $this->argument('subdomain')));

        foreach ($clients as $client) {
            $linked = $databases->forClientOr($client, function () use ($client) {
                $count = 0;
                foreach ([[Order::class, 'shipping_name'], [ServiceRequest::class, 'name']] as [$model, $nameColumn]) {
                    $model::withoutGlobalScopes()
                        ->where('client_id', $client->id)
                        ->whereNull('customer_id')
                        ->whereNotNull('email')->where('email', '!=', '')
                        ->orderBy('id')
                        ->each(function ($row) use ($client, $nameColumn, &$count) {
                            $customer = GuestCustomer::forEmail($row->email, $row->{$nameColumn}, $row->phone, $client->id);
                            if ($customer) {
                                $row->newQueryWithoutScopes()->whereKey($row->getKey())->update(['customer_id' => $customer->id]);
                                $count++;
                            }
                        });
                }

                return $count;
            }, null);

            $linked === null
                ? $this->warn("{$client->name}: database not ready, skipped.")
                : $this->line("{$client->name}: {$linked} linked.");
        }

        return self::SUCCESS;
    }
}
