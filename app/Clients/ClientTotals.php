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
        return Client::with(['storefront', 'plan'])->orderBy('name')->get()
            ->map(fn (Client $client) => self::for($client) + ['client' => $client]);
    }

    /** @return array{products: int, services: int, orders: int, revenue: int, customers: int, staff: int} */
    public static function for(Client $client): array
    {
        $databases = app(ClientDatabase::class);

        $read = fn () => [
            'products' => Product::withoutGlobalScopes()->whereNull('deleted_at')->where('client_id', $client->id)->count(),
            'services' => Service::withoutGlobalScopes()->whereNull('deleted_at')->where('client_id', $client->id)->count(),
            'orders' => Order::withoutGlobalScopes()->whereNull('deleted_at')->where('client_id', $client->id)->count(),
            'revenue' => (int) Order::withoutGlobalScopes()->whereNull('deleted_at')->where('client_id', $client->id)->where('status', '!=', 'cancelled')->sum('total'),
        ];
        $counts = $databases->isolated()
            ? $databases->forClientOr($client, $read, ['products' => 0, 'services' => 0, 'orders' => 0, 'revenue' => 0])
            : $read();

        return $counts + [
            'customers' => Customer::where('client_id', $client->id)->count(),
            'staff' => ClientUser::where('client_id', $client->id)->count(),
        ];
    }
}
