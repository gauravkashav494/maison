<?php

namespace App\Sync;

use App\Clients\ClientQuota;
use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Support\PhoneNumber;
use App\Support\SalesOrder;
use App\Support\TaxPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Applies what the local system sends. Field ownership lives here:
 *
 *   local system  product sku, name, price, compare-at price and stock; each variant's sku,
 *                 options and price; a product's category, brand, tax and HSN code (and a
 *                 variant's HSN code) while it has none; order fulfilment
 *   EcomVision    everything else on a product (slug, text, images, category, brand, tax and
 *                 HSN code once set, other categories, collections, visibility, variant images and
 *                 on-sale flags), orders as placed, and the profile of an existing customer
 *
 * Prices are rupees and paise: `price` in whichever form the store enters its prices (including
 * tax or before it, Site settings → Payments & tax), or `price_incl_tax` / `price_excl_tax` as
 * the local system holds them, converted to the store's form with the product's tax
 * (storePrice()). Product works out the other one from its tax.
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
            'compare_at_price' => $item['compare_at_price'] ?? null,
        ];

        try {
            if ($product) {
                $product->fill($owned);
                $tags = $this->fillBlanks($product, $item);
                // After fillBlanks: a price with or without tax is converted with the product's tax.
                $product->fill($this->storePrice($item, $this->rateOf($product)));
                if ($variants) {
                    $this->mergeVariants($product, $variants);
                }
                if (! $product->isDirty()) {
                    return SyncResult::of($key, SyncResult::UNCHANGED);
                }
                // Came from the local system, so it is not sent straight back to it.
                Product::withoutSyncTracking(fn () => $product->save());
                $this->rememberSkus($product);
                if ($tags !== null) {
                    $product->listUnder($tags);
                }

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
            $tags = $this->fillBlanks($product, $item);
            $product->fill($this->storePrice($item, $this->rateOf($product)));
            if ($variants) {
                $this->mergeVariants($product, $variants);
            }
            $product->save();
            $this->rememberSkus($product);
            if ($tags !== null) {
                $product->listUnder($tags);
            }

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
     * @param  array<int, array{sku:string, options:array<string,string>, price?:int|float|null}>  $incoming
     */
    private function mergeVariants(Product $product, array $incoming): void
    {
        $rate = $this->rateOf($product);
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

            // No price of its own (in any form): sells at the product's.
            $owned = ['sku' => $variant['sku'], 'options' => $options] + ($this->storePrice($variant, $rate) ?: ['price' => null]);
            // A variant's own HSN code, like the product's, only while it has none.
            $hsn = self::hsn($variant['hsn_code'] ?? null);
            if (isset($bySku[$variant['sku']])) {
                $index = $bySku[$variant['sku']];
                $variants[$index] = array_merge($variants[$index], $owned);
                if ($hsn && blank($variants[$index]['hsn_code'] ?? null)) {
                    $variants[$index]['hsn_code'] = $hsn;
                }
            } else {
                $bySku[$variant['sku']] = count($variants);
                $variants[] = $owned + ['stock' => 0, 'is_active' => true] + ($hsn ? ['hsn_code' => $hsn] : []);
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

    /**
     * The main category, brand and tax are filled in from the local system only while the
     * product has none; after that they are EcomVision's.
     *
     * The categories are the form's boxes: `category` and, under it, `category_2` make the main
     * category; `category_3` and `category_4` are the two category tags, any categories the
     * product is also listed under. Returns those tags' ids to list the product under once it is
     * saved, or null when the categories are left as they are.
     *
     * @return list<int>|null
     */
    private function fillBlanks(Product $product, array $item): ?array
    {
        $tags = null;
        if (blank($product->category_id) && filled($item['category'] ?? null)) {
            $product->category_id = $this->categoryId(array_filter([$item['category'], $item['category_2'] ?? null], 'filled'));
            $tags = array_map(
                fn (string $name) => $this->categoryId([$name]),
                array_values(array_filter([$item['category_3'] ?? null, $item['category_4'] ?? null], 'filled')),
            );
        }
        // Product::syncBrand() links the name to the client's brand, creating it when new.
        if (blank($product->brand_id) && blank($product->brand) && filled($item['brand'] ?? null)) {
            $product->brand = trim((string) $item['brand']);
        }
        if (blank($product->tax_id) && isset($item['tax_rate']) && is_numeric($item['tax_rate'])) {
            $product->tax_id = $this->taxId((float) $item['tax_rate']);
        }
        if (blank($product->hsn_code) && ($hsn = self::hsn($item['hsn_code'] ?? null))) {
            $product->hsn_code = $hsn;
        }

        return $tags;
    }

    /**
     * The price to save from what the local system sent (SyncController::prices()):
     *
     *   price           as the store enters its prices (ping's prices_include_tax): saved as it is
     *   price_incl_tax  including GST, as most shops hold it
     *   price_excl_tax  before GST
     *   both of these   as a shop that keeps both holds them
     *
     * The figure in the store's own form becomes `price` exactly (worked out through $rate when
     * only the other one is sent), so what the shopper pays is the local system's figure. Each
     * price sent is kept as it is in its own column; TaxPrices::sync keeps it while it agrees with
     * `price` and the product's tax to the paisa, and works it out afresh otherwise (a rounding
     * difference, or a tax rate EcomVision has changed). Empty when no price is sent.
     *
     * @param  array<string, mixed>  $values  an item, or a variant of one
     * @return array{price?: float, price_incl_tax?: float, price_excl_tax?: float}
     */
    private function storePrice(array $values, float $rate): array
    {
        if (isset($values['price'])) {
            return ['price' => (float) $values['price']];
        }

        $incl = isset($values['price_incl_tax']) ? round((float) $values['price_incl_tax'], 2) : null;
        $excl = isset($values['price_excl_tax']) ? round((float) $values['price_excl_tax'], 2) : null;
        if ($incl === null && $excl === null) {
            return [];
        }

        $incl ??= TaxPrices::convert($excl, $rate, toInclusive: true);
        $excl ??= TaxPrices::convert($incl, $rate, toInclusive: false);

        return ['price' => prices_include_tax() ? $incl : $excl, 'price_incl_tax' => $incl, 'price_excl_tax' => $excl];
    }

    /**
     * The GST rate the store charges on $product, which converts a price with or without tax: its
     * tax's rate (the one just picked from tax_rate when it had none), 0 with no tax.
     */
    private function rateOf(Product $product): float
    {
        if (! $product->tax_id) {
            return 0.0;
        }

        return (float) (Tax::query()->withTrashed()->whereKey($product->tax_id)->value('rate') ?? $product->tax_rate ?? 0);
    }

    /** "6109 10 00" → "61091000"; null when blank. Validated by SyncController::HSN. */
    private static function hsn(mixed $code): ?string
    {
        $code = preg_replace('/\s+/', '', (string) $code);

        return $code === '' ? null : $code;
    }

    /** The client's tax with this rate, created as "GST <rate>%" when it has none. */
    private function taxId(float $rate): int
    {
        $rate = round($rate, 2);
        $template = $this->client->templateId();

        $found = Tax::query()->where('client_id', $this->client->id)
            ->where(fn ($query) => $query->whereNull('template')->when($template, fn ($q) => $q->orWhere('template', $template)))
            ->where('rate', $rate)
            ->orderBy('id')
            ->value('id');

        return (int) ($found ?? Tax::create([
            'client_id' => $this->client->id,
            'template' => $template,
            'name' => 'GST '.Tax::rateLabel($rate),
            'rate' => $rate,
        ])->id);
    }

    /**
     * The deepest category of a path of names, as the form's category boxes pick it: a category
     * (Men), then a child of it (Shirts), and so on. Each missing one is created under the one
     * before it, kept out of the menu.
     *
     * @param  list<string>  $names
     */
    private function categoryId(array $names): int
    {
        $template = $this->client->templateId();
        $categories = Category::query()->where('client_id', $this->client->id)
            ->where(fn ($query) => $query->whereNull('template')->when($template, fn ($q) => $q->orWhere('template', $template)));
        $named = fn (string $name) => (clone $categories)->whereRaw('lower(name) = ?', [Str::lower($name)]);

        $parent = null;
        foreach (array_values($names) as $level => $name) {
            $name = trim($name);
            $found = match (true) {
                // The first may be any category, as on the form; a top-level one when two share the name.
                $level === 0 => $named($name)->orderByRaw('parent_id is not null')->orderBy('id')->first(),
                default => $named($name)->where('parent_id', $parent->id)->first(),
            };

            $parent = $found ?? Category::create(['show_in_menu' => false] + ($parent
                ? $parent->newChildAttributes($name)
                : ['client_id' => $this->client->id, 'template' => $template, 'name' => $name, 'is_active' => true]));
        }

        return (int) $parent->id;
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
        $bySku = (clone $base)->whereIn('sku', array_filter(array_column($items, 'sku')))->pluck('id', 'sku');
        $byExternalId = (clone $base)->whereIn('external_id', array_filter(array_column($items, 'external_id')))->pluck('id', 'external_id');

        $variantOwners = [];
        foreach ((clone $base)->where('has_variants', true)->get(['id', 'variants']) as $product) {
            foreach ((array) $product->variants as $index => $variant) {
                if (filled($variant['sku'] ?? null)) {
                    $variantOwners[(string) $variant['sku']][] = [$product->id, $index];
                }
            }
        }

        return array_map(function (array $item) use ($base, $bySku, $byExternalId, $variantOwners, $byProduct, $byVariant) {
            if (filled($item['external_id'] ?? null)) {
                return $this->stockByExternalId($base, $byExternalId, $item, $byProduct, $byVariant);
            }

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
                $product->fill($this->storePrice($item, $this->rateOf($product)));

                return $this->saved($product, $sku, $item);
            });
        }, $items);
    }

    /**
     * Stock for the product with the local system's own id, when it names one: a product without
     * variants needs no SKU; a product sold by variant needs the variant's SKU with it.
     *
     * @param  Builder<Product>  $base
     * @param  Collection<string, int>  $byExternalId
     */
    private function stockByExternalId(Builder $base, Collection $byExternalId, array $item, array $byProduct, array $byVariant): array
    {
        $key = filled($item['sku'] ?? null) ? $item['sku'] : $item['external_id'];
        $id = $byExternalId[$item['external_id']] ?? null;
        if (! $id) {
            return SyncResult::rejected($key, 'No product has this external_id.');
        }

        return (clone $base)->getConnection()->transaction(function () use ($base, $id, $item, $key, $byProduct, $byVariant) {
            $product = (clone $base)->lockForUpdate()->find($id);

            if ($product->sellsVariants()) {
                if (blank($item['sku'] ?? null)) {
                    return SyncResult::rejected($key, 'This product is sold by variant; send each variant\'s sku with the external_id.');
                }
                foreach ((array) $product->variants as $index => $variant) {
                    if ((string) ($variant['sku'] ?? '') === (string) $item['sku']) {
                        return $this->applyVariantStock($product, $index, $item, max(0, $item['on_hand'] - ($byVariant[$item['sku']] ?? 0)));
                    }
                }

                return SyncResult::rejected($key, 'This product has no variant with this SKU.');
            }

            $product->stock = max(0, $item['on_hand'] - ($byProduct[$product->id] ?? 0));
            $product->fill($this->storePrice($item, $this->rateOf($product)));

            return $this->saved($product, $key, $item);
        });
    }

    private function applyVariantStock(Product $product, int|string $index, array $item, int $stock): array
    {
        $variants = (array) $product->variants;
        $variants[$index]['stock'] = $stock;
        $variants[$index] = array_merge($variants[$index], $this->storePrice($item, $this->rateOf($product)));

        $product->variants = $variants;
        $product->stock = array_sum(array_map(fn (array $variant) => (int) ($variant['stock'] ?? 0), $variants));

        return $this->saved($product, $item['sku'], $item);
    }

    private function saved(Product $product, string $sku, array $item = []): array
    {
        if (! $product->isDirty()) {
            return SyncResult::of($sku, SyncResult::UNCHANGED);
        }
        // In the stock history with the local system's reason (stock received, a counter sale,
        // a count…), as the Adjust stock screen records it; "From local system" when it gives none.
        // Not a product-form edit either way.
        $product->stockChangeReason = StockMovement::LOCAL_REASONS[$item['reason'] ?? ''] ?? StockMovement::SYNCED;
        $product->stockChangeNote = $item['note'] ?? null;
        $product->stockChangeBy = 'Sync agent';
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
        $email = filled($item['email'] ?? null) ? Str::lower(trim($item['email'])) : null;
        $phone = filled($item['phone'] ?? null) ? (PhoneNumber::national('IN', $item['phone']) ?? trim($item['phone'])) : null;
        $customers = Customer::query()->where('client_id', $this->client->id);

        // By the local key, then the email, then the phone (in any of the forms it is stored in).
        $customer = (clone $customers)->where('external_id', $key)->first();
        $matchedBy = null;
        if (! $customer && $email && ($customer = (clone $customers)->whereRaw('lower(email) = ?', [$email])->first())) {
            $matchedBy = 'email';
        }
        if (! $customer && $phone && ($customer = SalesOrder::existingCustomerByPhone($this->client->id, $phone))) {
            $matchedBy = 'phone';
        }

        if (! $customer) {
            // An email another (deleted) customer still holds is left off rather than failing the row.
            if ($email && Customer::withTrashed()->where('client_id', $this->client->id)->whereRaw('lower(email) = ?', [$email])->exists()) {
                $email = null;
            }
            if (! $email && ! $phone) {
                return SyncResult::rejected($key, 'That email belongs to a deleted customer; send a phone number too.');
            }
            // No password: they sign in with "forgot password" or a WhatsApp code to their phone.
            $customer = new Customer(['client_id' => $this->client->id, 'name' => $item['name'], 'email' => $email, 'phone' => $phone, 'is_active' => true]);
            $customer->forceFill(['external_id' => $key, 'sync_acked_version' => 1])->save();

            return SyncResult::of($key, SyncResult::CREATED, ['id' => $customer->id]);
        }

        if (filled($customer->external_id) && $customer->external_id !== $key) {
            return SyncResult::rejected($key, 'That '.($matchedBy ?? 'email').' belongs to a customer with another external_id.');
        }

        $customer->external_id = $key;
        if (blank($customer->phone) && $phone) {
            $customer->phone = $phone;
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

        $hadInvoice = filled($order->invoice_number);

        $result = Order::withoutSyncTracking(function () use ($item, $key, $order, $status) {
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

        // Dispatching issues the tax invoice (Order::assignInvoiceNumber). The local system's own
        // change is not echoed, but the invoice number is new to it, so the order is offered again.
        if (! $hadInvoice && filled($order->invoice_number)) {
            Order::query()->whereKey($order->getKey())->toBase()->increment('sync_version');
            $result['invoice_number'] = $order->invoice_number;
        }

        return $result;
    }
}
