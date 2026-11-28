<?php

namespace App\Sync;

use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\TaxPrices;
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
            ->when($resource === 'orders', fn (Builder $q) => $q->with(['items.product', 'salesperson', 'customer']))
            ->when($resource === 'products', fn (Builder $q) => $q->with(['category', 'categories']))
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
        // In the product form's order (Details, then Pricing & Variants), so a field is easy to find there.
        $path = $product->category ? $product->category->lineage() : [];
        // The category tags: the other categories it is listed under, in the order they were added.
        $tags = $product->categories->sortBy('id')
            ->reject(fn (Category $category) => in_array($category->id, array_map(fn (Category $c) => $c->id, $path), true))
            ->values();

        return $product->only(['id', 'external_id', 'name', 'slug', 'brand']) + [
            // As the form's boxes: the top category (Men) and the main category under it (Shirts),
            // then two category tags. Then every category the product is listed under.
            'category' => $path[0]->name ?? null,
            'category_2' => count($path) > 1 ? end($path)->name : null,
            'category_3' => $tags->get(0)?->name,
            'category_4' => $tags->get(1)?->name,
            'categories' => $product->categories->pluck('name')->sort()->values()->all(),
        ] + $product->only([
            'description', 'is_active', 'compare_at_price', 'tax_rate', 'hsn_code', 'price_incl_tax', 'price_excl_tax',
            'price', 'sku', 'has_variants',
        ]) + [
            // Each variant in the form's column order, with its price with and without tax too (the
            // product's tax: one rate for all).
            'variants' => $product->sellsVariants() ? array_map(
                fn (array $variant) => self::variantInFormOrder($variant + TaxPrices::for($variant['price'], $product->effectiveTaxRate(), prices_include_tax())),
                $product->variantList(),
            ) : [],
            'version' => $product->sync_version,
        ];
    }

    /** A variant's fields as the variant table on the product form lists them; any others follow. */
    private static function variantInFormOrder(array $variant): array
    {
        $order = ['options', 'label', 'sku', 'hsn_code', 'compare_at_price', 'price_incl_tax', 'price_excl_tax', 'price', 'stock', 'is_active', 'images'];

        return array_replace(array_intersect_key(array_flip($order), $variant), $variant);
    }

    private function presentOrders(Order $order): array
    {
        return $order->only([
            'id', 'external_id', 'number', 'customer_id', 'status', 'email', 'phone', 'shipping_name', 'shipping_line1',
            'shipping_line2', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country',
            'shipping_method', 'shipping_cost', 'payment_method', 'payment_status', 'subtotal', 'discount', 'coupon_code',
            // The promo code's offer as it stood when the order was placed: "percent" or "fixed", and its value.
            'coupon_type', 'coupon_value',
            'tax', 'tax_rate', 'tax_label', 'prices_include_tax', 'total', 'carrier', 'tracking_number', 'notes',
            'cancellation_reason', 'invoice_number',
        ]) + [
            'version' => $order->sync_version,
            // The shopper's and each line's record in the local system, once linked (null before).
            'customer_external_id' => $order->customer?->external_id,
            'placed_at' => $order->created_at?->toIso8601String(),
            'invoiced_at' => $order->invoiced_at?->toIso8601String(),
            // Placed by a sales person at the counter: no delivery, the goods left with the customer.
            'salesperson' => $order->salesperson?->name,
            'items' => $order->items->map(fn (OrderItem $item) => $item->only([
                'product_id', 'name', 'sku', 'variant_sku', 'hsn_code', 'options', 'size', 'color', 'price', 'qty', 'total',
                'tax_rate', 'tax',
            ]) + ['product_external_id' => $item->product?->external_id])->all(),
        ];
    }

    private function presentCustomers(Customer $customer): array
    {
        return $customer->only(['id', 'external_id', 'name', 'email', 'phone', 'is_active']) + ['version' => $customer->sync_version];
    }
}
