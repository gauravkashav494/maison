<?php

namespace App\Sync;

use App\Clients\ClientQuota;
use App\Models\Category;
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
 *   local system  product sku, name, price, compare-at price and stock; each variant's sku,
 *                 options and price; a product's category while it has none; order fulfilment
 *   EcomVision    everything else on a product (slug, text, images, category once set,
 *                 collections, visibility, variant images and on-sale flags),
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

        $variants = $item['variants'] ?? [];
        if ($variants && ($taken = $this->variantSkuTaken($variants, $product, $item['sku'] ?? null))) {
            return SyncResult::rejected($key, "The variant SKU {$taken} is already used by another product.");
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
                // The category is only filled in while blank; after that it is EcomVision's.
                if (blank($product->category_id) && filled($item['category'] ?? null)) {
                    $product->category_id = $this->categoryId($item['category']);
                }
                if ($variants) {
                    $this->mergeVariants($product, $variants);
                }
                if (! $product->isDirty()) {
                    return SyncResult::of($key, SyncResult::UNCHANGED);
                }
                // Came from the local system, so it is not sent straight back to it.
                Product::withoutSyncTracking(fn () => $product->save());
                $this->rememberSkus($product);

                return SyncResult::of($key, SyncResult::UPDATED, ['id' => $product->id]);
            }

            if (ClientQuota::for($this->client)->atProductLimit()) {
                return SyncResult::rejected($key, 'The client has reached its product limit.');
            }

            // Hidden until someone adds its images and text on EcomVision.
            $product = new Product($owned + [
                'client_id' => $this->client->id,
                'template' => $this->client->templateId(),
                'slug' => $this->uniqueSlug($item['name']),
                'stock' => 0,
                'is_active' => false,
                'sync_acked_version' => 1,
            ]);
            if (filled($item['category'] ?? null)) {
                $product->category_id = $this->categoryId($item['category']);
            }
            if ($variants) {
                $this->mergeVariants($product, $variants);
            }
            $product->save();
            $this->rememberSkus($product);

            return SyncResult::of($key, SyncResult::CREATED, ['id' => $product->id]);
        } catch (UniqueConstraintViolationException) {
            return SyncResult::rejected($key, 'The SKU is already used by another product.');
        }
    }

    /** @var array<string, array<int, int>>|null SKU => ids of the products that use it */
    private ?array $skuOwners = null;

    /** The first variant SKU that another product already uses as its own or a variant's SKU. */
    private function variantSkuTaken(array $variants, ?Product $product, ?string $sku): ?string
    {
        if ($this->skuOwners === null) {
            $this->skuOwners = [];
            Product::query()->where('client_id', $this->client->id)->get(['id', 'sku', 'variants'])
                ->each(fn (Product $existing) => $this->rememberSkus($existing));
        }

        foreach ($variants as $variant) {
            $owners = array_diff($this->skuOwners[$variant['sku']] ?? [], [$product?->id]);
            if ($owners) {
                return $variant['sku'];
            }
        }

        return null;
    }

    private function rememberSkus(Product $product): void
    {
        if ($this->skuOwners === null) {
            return;
        }

        foreach ($this->skuOwners as $sku => $ids) {
            $this->skuOwners[$sku] = array_values(array_diff($ids, [$product->id]));
        }
        $skus = array_filter([$product->sku, ...array_map(fn ($v) => $v['sku'] ?? null, (array) $product->variants)], 'filled');
        foreach (array_unique(array_map('strval', $skus)) as $sku) {
            $this->skuOwners[$sku][] = $product->id;
        }
    }

    /**
     * Adds or updates variants by SKU. The local system owns each variant's SKU, options and
     * price; stock arrives through the stock push, and images, the on-sale flag and any
     * variant the local system no longer sends stay as EcomVision has them.
     *
     * @param  array<int, array{sku:string, options:array<string,string>, price?:int|null}>  $incoming
     */
    private function mergeVariants(Product $product, array $incoming): void
    {
        $attributes = $product->variantAttributes();
        $variants = array_values((array) $product->variants);

        $bySku = [];
        foreach ($variants as $index => $variant) {
            if (filled($variant['sku'] ?? null)) {
                $bySku[(string) $variant['sku']] = $index;
            }
        }

        foreach ($incoming as $variant) {
            $options = [];
            foreach ($variant['options'] as $name => $value) {
                [$name, $value] = $this->attributeValue($attributes, trim((string) $name), trim((string) $value));
                if ($name !== '' && $value !== '') {
                    $options[$name] = $value;
                }
            }
            if (! $options) {
                continue;
            }

            $owned = ['sku' => $variant['sku'], 'options' => $options, 'price' => $variant['price'] ?? null];
            if (isset($bySku[$variant['sku']])) {
                $variants[$bySku[$variant['sku']]] = array_merge($variants[$bySku[$variant['sku']]], $owned);
            } else {
                $bySku[$variant['sku']] = count($variants);
                $variants[] = $owned + ['stock' => 0, 'is_active' => true];
            }
        }

        $product->variant_attributes = $attributes;
        $product->variants = $variants;
        $product->has_variants = true;
    }

    /**
     * The attribute name and value as the product already spells them, adding either when new,
     * so "size: m" from the local system finds the existing "Size: M".
     *
     * @return array{0:string, 1:string}
     */
    private function attributeValue(array &$attributes, string $name, string $value): array
    {
        if ($name === '' || $value === '') {
            return ['', ''];
        }

        foreach ($attributes as &$attribute) {
            if (strcasecmp($attribute['name'], $name) !== 0) {
                continue;
            }
            foreach ($attribute['values'] as $known) {
                if (strcasecmp($known, $value) === 0) {
                    return [$attribute['name'], $known];
                }
            }
            $attribute['values'][] = $value;

            return [$attribute['name'], $value];
        }
        unset($attribute);

        $attributes[] = ['name' => $name, 'values' => [$value], 'swatches' => []];

        return [$name, $value];
    }

    /** The client's category with this name, created (and kept out of the menu) when missing. */
    private function categoryId(string $name): int
    {
        $name = trim($name);
        $template = $this->client->templateId();
        $categories = Category::query()->where('client_id', $this->client->id)
            ->where(fn ($query) => $query->whereNull('template')->when($template, fn ($q) => $q->orWhere('template', $template)));

        $found = (clone $categories)->whereRaw('lower(name) = ?', [Str::lower($name)])->value('id');
        if ($found) {
            return (int) $found;
        }

        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        for ($n = 2; Category::withTrashed()->where('client_id', $this->client->id)->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return (int) Category::create([
            'client_id' => $this->client->id,
            'template' => $template,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'show_in_menu' => false,
        ])->id;
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
