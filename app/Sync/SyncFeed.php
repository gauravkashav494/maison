<?php

namespace App\Sync;

use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/** What EcomVision has for the local system, and the record of what the agent has taken. */
class SyncFeed
{
    public const RESOURCES = ['products' => Product::class, 'orders' => Order::class, 'customers' => Customer::class];

    public function __construct(private readonly Client $client) {}

    private function query(string $resource): Builder
    {
        return (self::RESOURCES[$resource])::query()->where('client_id', $this->client->id);
    }

    /** @return array{data: array<int, array<string, mixed>>, has_more: bool} */
    public function pending(string $resource, int $limit, int $afterId): array
    {
        $rows = $this->query($resource)
            ->when($resource === 'orders', fn (Builder $q) => $q->with('items'))
            ->whereColumn('sync_version', '>', 'sync_acked_version')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        return [
            'data' => $rows->take($limit)->map(fn ($row) => $this->{'present'.ucfirst($resource)}($row))->values()->all(),
            'has_more' => $rows->count() > $limit,
        ];
    }

    /** @param array<string, mixed> $item */
    public function acknowledge(string $resource, array $item): array
    {
        $row = $this->query($resource)->find($item['id']);
        if (! $row) {
            return SyncResult::rejected($item['id'], 'Record not found.');
        }
        if ($item['version'] > $row->sync_version) {
            return SyncResult::rejected($item['id'], 'That version has not been issued.');
        }

        $changes = [];
        if ($item['version'] > $row->sync_acked_version) {
            $changes['sync_acked_version'] = $item['version'];
        }
        $external = $item['external_id'] ?? null;
        if (filled($external) && $external !== $row->external_id) {
            if ($this->query($resource)->where('external_id', $external)->exists()) {
                return SyncResult::rejected($item['id'], 'That external_id belongs to another record.');
            }
            $changes['external_id'] = $external;
        }

        if (! $changes) {
            return SyncResult::of($item['id'], SyncResult::UNCHANGED);
        }

        // Written straight to the table: taking a record must not count as changing it.
        $this->query($resource)->whereKey($row->getKey())->toBase()->update($changes);

        return SyncResult::of($item['id'], SyncResult::UPDATED);
    }

    private function presentProducts(Product $product): array
    {
        return $product->only(['id', 'external_id', 'sku', 'name', 'slug', 'description', 'price', 'compare_at_price', 'is_active', 'has_variants'])
            + ['version' => $product->sync_version, 'variants' => $product->sellsVariants() ? $product->variantList() : []];
    }

    private function presentOrders(Order $order): array
    {
        return $order->only([
            'id', 'external_id', 'number', 'customer_id', 'status', 'email', 'phone', 'shipping_name', 'shipping_line1',
            'shipping_line2', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country',
            'shipping_method', 'shipping_cost', 'payment_method', 'payment_status', 'subtotal', 'discount', 'coupon_code',
            'tax', 'total', 'carrier', 'tracking_number', 'notes', 'cancellation_reason',
        ]) + [
            'version' => $order->sync_version,
            'placed_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(fn (OrderItem $item) => $item->only([
                'product_id', 'name', 'sku', 'variant_sku', 'options', 'size', 'color', 'price', 'qty', 'total',
            ]))->all(),
        ];
    }

    private function presentCustomers(Customer $customer): array
    {
        return $customer->only(['id', 'external_id', 'name', 'email', 'phone', 'is_active']) + ['version' => $customer->sync_version];
    }
}
