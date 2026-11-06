<?php

namespace App\Clients;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Counts per client for the SuperAdmin: numbers only, never a customer, an order or a product.
 * Works whether the clients share a database or each have their own.
 */
class ClientTotals
{
    /** @return Collection<int, array> one row per client */
    public static function all(): Collection
    {
        $databases = app(ClientDatabase::class);

        return Client::with(['storefront', 'plan'])->orderBy('name')->get()->map(function (Client $client) use ($databases) {
            $read = fn () => [
                'products' => Product::withoutGlobalScopes()->where('client_id', $client->id)->count(),
                'services' => Service::withoutGlobalScopes()->where('client_id', $client->id)->count(),
                'orders' => Order::withoutGlobalScopes()->where('client_id', $client->id)->count(),
                'revenue' => (int) Order::withoutGlobalScopes()->where('client_id', $client->id)->where('status', '!=', 'cancelled')->sum('total'),
            ];
            $counts = $databases->isolated() ? $databases->forClient($client, $read) : $read();

            return $counts + [
                'client' => $client,
                'customers' => Customer::where('client_id', $client->id)->count(),
                'staff' => ClientUser::where('client_id', $client->id)->count(),
            ];
        });
    }
}
