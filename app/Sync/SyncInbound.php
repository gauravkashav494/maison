<?php

namespace App\Sync;

use App\Clients\ClientQuota;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Applies what the local system sends. Field ownership lives here:
 *
 *   local system  product sku, name, price, compare-at price and stock; order fulfilment
 *   EcomVision        everything else on a product (slug, text, images, category, visibility),
 *                 orders as placed, and the profile of an existing customer
 */
class SyncInbound
{
    public function __construct(private readonly Client $client) {}

    /** @param array<string, mixed> $item */
    public function product(array $item): array
    {
        $key = $item['external_id'];
        $products = Product::query()->where('client_id', $this->client->id);

        $product = (clone $products)->where('external_id', $key)->first();
        if (! $product && filled($item['sku'] ?? null)) {
            $product = (clone $products)->where('sku', $item['sku'])->first();
            if ($product && filled($product->external_id)) {
                return SyncResult::rejected($key, 'That SKU belongs to a product with another external_id.');
            }
        }

        $owned = [
            'external_id' => $key,
            'sku' => $item['sku'] ?? null,
            'name' => $item['name'],
            'price' => $item['price'],
            'compare_at_price' => $item['compare_at_price'] ?? null,
        ];

        try {
            if ($product) {
                $product->fill($owned);
                if (! $product->isDirty()) {
                    return SyncResult::of($key, SyncResult::UNCHANGED);
                }
                $product->save();

                return SyncResult::of($key, SyncResult::UPDATED, ['id' => $product->id]);
            }

            if (ClientQuota::for($this->client)->atProductLimit()) {
                return SyncResult::rejected($key, 'The client has reached its product limit.');
            }

            // Hidden until someone adds its images and text on EcomVision.
            $product = Product::create($owned + [
                'client_id' => $this->client->id,
                'template' => $this->client->templateId(),
                'slug' => $this->uniqueSlug($item['name']),
                'stock' => 0,
                'is_active' => false,
                'sync_acked_version' => 1,
            ]);

            return SyncResult::of($key, SyncResult::CREATED, ['id' => $product->id]);
        } catch (UniqueConstraintViolationException) {
            return SyncResult::rejected($key, 'The SKU is already used by another product.');
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $products = Product::query()->where('client_id', $this->client->id);

        for ($n = 2; (clone $products)->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /**
     * Sets stock from the local count, less what EcomVision has sold that the local system has not
     * yet taken in; those orders are still in its count.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function stock(array $items): array
    {
        [$byProduct, $byVariant] = $this->undeliveredQuantities();

        $base = Product::query()->where('client_id', $this->client->id);
        $bySku = (clone $base)->whereIn('sku', array_column($items, 'sku'))->pluck('id', 'sku');

        $variantOwners = [];
        foreach ((clone $base)->where('has_variants', true)->get(['id', 'variants']) as $product) {
            foreach ((array) $product->variants as $index => $variant) {
                if (filled($variant['sku'] ?? null)) {
                    $variantOwners[(string) $variant['sku']][] = [$product->id, $index];
                }
            }
        }

        return array_map(function (array $item) use ($base, $bySku, $variantOwners, $byProduct, $byVariant) {
            $sku = $item['sku'];
            $owners = $variantOwners[$sku] ?? [];
            if (count($owners) > 1) {
                return SyncResult::rejected($sku, 'More than one variant has this SKU.');
            }

            $connection = (clone $base)->getConnection();

            return $connection->transaction(function () use ($item, $sku, $base, $bySku, $owners, $byProduct, $byVariant) {
                if ($owners) {
                    [$id, $index] = $owners[0];
                    $product = (clone $base)->lockForUpdate()->find($id);

                    return $this->applyVariantStock($product, $index, $item, max(0, $item['on_hand'] - ($byVariant[$sku] ?? 0)));
                }

                $product = isset($bySku[$sku]) ? (clone $base)->lockForUpdate()->find($bySku[$sku]) : null;
                if (! $product) {
                    return SyncResult::rejected($sku, 'No product or variant has this SKU.');
                }
                if ($product->sellsVariants()) {
                    return SyncResult::rejected($sku, 'This product is sold by variant; send the variant SKUs.');
                }

                $product->stock = max(0, $item['on_hand'] - ($byProduct[$product->id] ?? 0));
                if (isset($item['price'])) {
                    $product->price = $item['price'];
                }

                return $this->saved($product, $sku);
            });
        }, $items);
    }

    private function applyVariantStock(Product $product, int|string $index, array $item, int $stock): array
    {
        $variants = (array) $product->variants;
        $variants[$index]['stock'] = $stock;
        if (isset($item['price'])) {
            $variants[$index]['price'] = $item['price'];
        }

        $product->variants = $variants;
        $product->stock = array_sum(array_map(fn (array $variant) => (int) ($variant['stock'] ?? 0), $variants));

        return $this->saved($product, $item['sku']);
    }

    private function saved(Product $product, string $sku): array
    {
        if (! $product->isDirty()) {
            return SyncResult::of($sku, SyncResult::UNCHANGED);
        }
        $product->save();

        return SyncResult::of($sku, SyncResult::UPDATED, ['stock' => (int) $product->stock]);
    }

    /** @return array{0: array<int, int>, 1: array<string, int>} sold quantities not yet taken */
    private function undeliveredQuantities(): array
    {
        $byProduct = [];
        $byVariant = [];

        OrderItem::query()
            ->whereHas('order', fn ($orders) => $orders
                ->where('client_id', $this->client->id)
                ->where('sync_acked_version', 0)
                ->where('status', '!=', Order::STATUS_CANCELLED))
            ->get(['product_id', 'variant_sku', 'qty'])
            ->each(function (OrderItem $line) use (&$byProduct, &$byVariant) {
                $byProduct[$line->product_id] = ($byProduct[$line->product_id] ?? 0) + $line->qty;
                if (filled($line->variant_sku)) {
                    $byVariant[$line->variant_sku] = ($byVariant[$line->variant_sku] ?? 0) + $line->qty;
                }
            });

        return [$byProduct, $byVariant];
    }

    /** Adds a customer known only locally. An existing profile is the shopper's own to edit. */
    public function customer(array $item): array
    {
        $key = $item['external_id'];
        $email = Str::lower($item['email']);
        $customers = Customer::query()->where('client_id', $this->client->id);

        $customer = (clone $customers)->where('external_id', $key)->first()
            ?? (clone $customers)->whereRaw('lower(email) = ?', [$email])->first();

        if (! $customer) {
            // No password: they can sign in only after using "forgot password".
            $customer = new Customer(['client_id' => $this->client->id, 'name' => $item['name'], 'email' => $email, 'phone' => $item['phone'] ?? null, 'is_active' => true]);
            $customer->forceFill(['external_id' => $key, 'sync_acked_version' => 1])->save();

            return SyncResult::of($key, SyncResult::CREATED, ['id' => $customer->id]);
        }

        if (filled($customer->external_id) && $customer->external_id !== $key) {
            return SyncResult::rejected($key, 'That email belongs to a customer with another external_id.');
        }

        $customer->external_id = $key;
        if (blank($customer->phone) && filled($item['phone'] ?? null)) {
            $customer->phone = $item['phone'];
        }
        if (! $customer->isDirty()) {
            return SyncResult::of($key, SyncResult::UNCHANGED);
        }
        Customer::withoutSyncTracking(fn () => $customer->save());

        return SyncResult::of($key, SyncResult::UPDATED, ['id' => $customer->id]);
    }

    /** Fulfilment from the local system, through the order's own transition rules. */
    public function orderStatus(array $item): array
    {
        $key = $item['number'];
        $order = Order::query()->where('client_id', $this->client->id)->where('number', $key)->first();
        if (! $order) {
            return SyncResult::rejected($key, 'Order not found.');
        }

        $status = $item['status'] ?? $order->status;
        if ($status !== $order->status && ! $order->canTransitionTo($status)) {
            return SyncResult::rejected($key, "An order that is {$order->status} cannot move to {$status}.");
        }

        return Order::withoutSyncTracking(function () use ($item, $key, $order, $status) {
            $changed = false;
            if ($status !== $order->status) {
                $order->transitionTo($status, $item['note'] ?? 'Updated by the local system');
                $changed = true;
            }

            $order->fill(array_intersect_key($item, array_flip(['carrier', 'tracking_number'])));
            if ($order->isDirty()) {
                $order->save();
                $changed = true;
            }

            return SyncResult::of($key, $changed ? SyncResult::UPDATED : SyncResult::UNCHANGED);
        });
    }
}
