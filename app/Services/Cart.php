<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Support\SalesOrder;
use App\Support\TaxBreakdown;
use Illuminate\Support\Facades\Session;

/**
 * Session-backed shopping bag. Items are keyed by product + size + colour so the same
 * product in different variants stays as separate lines. Also tracks the applied coupon
 * and chosen shipping method so the cart, drawer and checkout all agree on totals.
 */
class Cart
{
    private const KEY = 'cart.items';

    private const COUPON = 'cart.coupon';

    private const SHIPPING = 'cart.shipping';

    /** Names of lines whose product (or chosen variant) is no longer sold, not yet told to the shopper in a toast. */
    private const UNAVAILABLE = 'cart.unavailable';

    /** Keys of the unavailable lines already told about, so each is said once while it stays in the cart. */
    private const UNAVAILABLE_TOLD = 'cart.unavailable_told';

    /** Messages about lines lowered or removed at checkout because the stock ran out, not yet told to the shopper. */
    private const STOCK = 'cart.stock';

    private const STOCK_PAGE = 'cart.stock_page'; // the same, for the box on the cart or checkout page

    /** @return array<string, array{product_id:int,options:array<string,string>,size:?string,color:?string,qty:int}> */
    public function raw(): array
    {
        return array_map(function ($line) {
            // Lines saved before variants existed carry only a size and a colour.
            $line['options'] ??= array_filter([
                'Size' => $line['size'] ?? null,
                'Colour' => $line['color'] ?? null,
            ], fn ($value) => filled($value));

            return $line;
        }, Session::get(self::KEY, []));
    }

    /**
     * Adds a product in the chosen combination. The options are whatever the product varies
     * by (Size, Colour, Material, …) and must already have been checked against the product;
     * size and colour are kept alongside them so older views and order lines still work.
     *
     * @param  array<string,string>  $options
     */
    public function add(int $productId, array $options, int $qty = 1): void
    {
        $items = $this->raw();
        $key = $this->key($productId, $options);
        $items[$key] = [
            'product_id' => $productId,
            'options' => $options,
            'size' => $this->option($options, 'size'),
            'color' => $this->option($options, 'colour', 'color'),
            'qty' => ($items[$key]['qty'] ?? 0) + max(1, $qty),
        ];
        $items = [$key => $items[$key]] + $items; // most recently added first
        Session::put(self::KEY, $items);
    }

    /** How many of this product and choice the cart already holds. */
    public function quantityOf(int $productId, array $options): int
    {
        return (int) ($this->raw()[$this->key($productId, $options)]['qty'] ?? 0);
    }

    /** First option whose attribute name matches one of these (case-insensitively). */
    private function option(array $options, string ...$names): ?string
    {
        foreach ($options as $attribute => $value) {
            foreach ($names as $name) {
                if (strcasecmp((string) $attribute, $name) === 0) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    public function update(string $key, int $qty): void
    {
        $items = $this->raw();
        if (! isset($items[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['qty'] = $qty;
        }
        Session::put(self::KEY, $items);
    }

    public function remove(string $key): void
    {
        $this->update($key, 0);
    }

    public function clear(): void
    {
        Session::forget([self::KEY, self::COUPON, self::SHIPPING]);
    }

    public function isEmpty(): bool
    {
        return empty($this->raw());
    }

    // ---- Coupons -----------------------------------------------------------

    public function applyCoupon(string $code): ?Coupon
    {
        $coupon = Coupon::findValid($code);
        if ($coupon) {
            Session::put(self::COUPON, $coupon->code);
        }

        return $coupon;
    }

    public function removeCoupon(): void
    {
        Session::forget(self::COUPON);
    }

    /** The code the shopper applied, still there when the coupon itself can no longer be used. */
    public function appliedCouponCode(): ?string
    {
        return Session::get(self::COUPON);
    }

    public function coupon(): ?Coupon
    {
        $code = Session::get(self::COUPON);

        return $code ? Coupon::findValid($code) : null;
    }

    // ---- Shipping ----------------------------------------------------------

    /** @return array<int, array{code:string,name:string,description:?string,cost:float,free_over:?float,eta:?string}> */
    public function shippingMethods(): array
    {
        // A sales person's order is not delivered: one free "method", which every total then uses.
        if (SalesOrder::active()) {
            return [SalesOrder::shippingMethod()];
        }

        $methods = setting('checkout.shipping_methods', []);
        if (empty($methods)) {
            $methods = [['code' => 'standard', 'name' => 'Standard delivery', 'description' => 'Tracked, 3–5 business days', 'cost' => 250, 'free_over' => (float) setting('site.free_shipping_threshold', 0), 'eta' => '3–5 business days']];
        }

        return array_map(fn ($m) => [
            'code' => $m['code'] ?? str($m['name'])->slug()->toString(),
            'name' => $m['name'] ?? 'Delivery',
            'description' => $m['description'] ?? null,
            'cost' => round((float) ($m['cost'] ?? 0), 2),
            'free_over' => isset($m['free_over']) && $m['free_over'] !== '' ? (float) $m['free_over'] : null,
            'eta' => $m['eta'] ?? null,
        ], array_values($methods));
    }

    public function setShippingMethod(string $code): void
    {
        Session::put(self::SHIPPING, $code);
    }

    public function shippingMethod(): array
    {
        $methods = $this->shippingMethods();
        $code = Session::get(self::SHIPPING);
        foreach ($methods as $m) {
            if ($m['code'] === $code) {
                return $m;
            }
        }

        return $methods[0];
    }

    // ---- Lines no longer sold -----------------------------------------------

    /**
     * The lines that cannot be ordered now: the product was hidden or deleted, or the chosen variant
     * switched off, since they were added ("No longer available"), or it has sold out ("Out of
     * stock"). They stay in the cart, shown greyed with that in red, out of the totals, until the
     * shopper removes them; no order is placed while any are left. A line that is sold again (or
     * restocked) simply comes back.
     *
     * @param  array<string, array>  $lines  key => raw line with its `reason` (unavailable | out_of_stock)
     * @return array<int, array{key:string, name:string, options_label:string, qty:int, image:?string, url:?string, reason:string, reason_label:string}>
     */
    private function describeUnavailable(array $lines): array
    {
        // Names and photos come from the products as they are now, trashed ones included.
        $products = Product::withTrashed()->whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');

        $told = Session::get(self::UNAVAILABLE_TOLD, []);
        $toTell = [];
        $rows = [];
        foreach ($lines as $key => $line) {
            $product = $products[$line['product_id']] ?? null;
            $options = array_filter((array) ($line['options'] ?? []), 'filled');
            $name = $product?->name ?? 'An item';
            $rows[] = [
                'key' => $key,
                'name' => $name,
                'options_label' => implode(' / ', array_map(fn ($n, $v) => $n.': '.$v, array_keys($options), array_values($options))),
                'qty' => (int) $line['qty'],
                'image' => $product?->image_url,
                'url' => $product && $product->is_active && ! $product->trashed() ? $product->url : null,
                'reason' => $line['reason'],
                'reason_label' => $line['reason'] === 'out_of_stock' ? 'Out of stock' : 'No longer available',
            ];
            // Told again if the reason changes (sold out, then hidden).
            $toldAs = $key.':'.$line['reason'];
            if (! in_array($toldAs, $told, true)) {
                $toTell[] = ['name' => $options !== [] ? $name.' ('.implode(' / ', $options).')' : $name, 'reason' => $line['reason']];
                $told[] = $toldAs;
            }
        }

        // Each newly unavailable line is said once in a toast; it stays listed in the cart.
        $current = array_map(fn ($key) => $key.':'.$lines[$key]['reason'], array_keys($lines));
        Session::put(self::UNAVAILABLE_TOLD, array_values(array_intersect($told, $current)));
        if ($toTell !== []) {
            Session::put(self::UNAVAILABLE, [...Session::get(self::UNAVAILABLE, []), ...$toTell]);
        }

        return $rows;
    }

    /** Whether the cart holds lines no longer sold, which the shopper must remove before ordering. */
    public function hasUnavailableItems(): bool
    {
        return $this->toArray()['unavailable'] !== [];
    }

    /**
     * Lowers lines to the stock left when the order was placed (PlaceOrder), and remembers what
     * changed until the shopper has been told. A line with none left stays as it is: the cart now
     * lists it greyed as "Out of stock" for the shopper to remove (describeUnavailable()), and
     * says so in its own toast.
     *
     * @param  array<string, int>  $available  line key => how many are left for it
     */
    public function limitToStock(array $available): void
    {
        $items = $this->raw();
        $names = Product::withTrashed()->whereIn('id', array_column(array_intersect_key($items, $available), 'product_id'))->pluck('name', 'id');

        $messages = [];
        foreach ($available as $key => $left) {
            if (! isset($items[$key]) || $left <= 0) {
                continue;
            }
            $line = $items[$key];
            $options = implode(' / ', array_filter((array) ($line['options'] ?? []), 'filled'));
            $name = ($names[$line['product_id']] ?? 'An item').($options !== '' ? ' ('.$options.')' : '');
            $messages[] = "Only {$left} of {$name} ".($left === 1 ? 'is' : 'are').' left, so your cart has been updated.';
            $this->update($key, $left);
        }

        foreach ([self::STOCK, self::STOCK_PAGE] as $notice) {
            Session::put($notice, [...Session::get($notice, []), ...$messages]);
        }
    }

    /**
     * Takes the coupon off the cart when the order could not use it after all (its last use went
     * to someone else, or this customer's uses are spent), with a note for the checkout page. Like
     * a stock change, the note stops the next "Place order" until the shopper has seen the new total.
     */
    public function dropCoupon(string $message): void
    {
        $this->removeCoupon();

        foreach ([self::STOCK, self::STOCK_PAGE] as $notice) {
            Session::put($notice, [...Session::get($notice, []), $message]);
        }
    }

    /** Whether lines were lowered or removed for stock (or the coupon taken off) that the shopper has not been told about yet. */
    public function hasUnavailableNotice(): bool
    {
        return Session::get(self::STOCK, []) !== [];
    }

    /** Stock changes from checkout ("Only 2 of X are left…"), once, for a toast; null when nothing changed. */
    public function takeUnavailableNotice(): ?string
    {
        return self::joined(Session::pull(self::STOCK, []));
    }

    /**
     * "X is no longer available. Y is out of stock. Please remove them from your cart.", once per
     * line newly unable to be ordered, for a toast.
     */
    public function takeUnavailableToast(): ?string
    {
        $told = array_filter(Session::pull(self::UNAVAILABLE, []), 'is_array');
        if ($told === []) {
            return null;
        }
        $sentences = [];
        foreach (['unavailable' => 'no longer available', 'out_of_stock' => 'out of stock'] as $reason => $words) {
            $names = array_values(array_unique(array_column(array_filter($told, fn ($t) => $t['reason'] === $reason), 'name')));
            if ($names !== []) {
                $list = count($names) === 1 ? $names[0] : implode(', ', array_slice($names, 0, -1)).' and '.end($names);
                $sentences[] = $list.(count($names) === 1 ? ' is ' : ' are ').$words.'.';
            }
        }
        $many = count($told) > 1;

        return implode(' ', $sentences).' Please remove '.($many ? 'them' : 'it').' from your cart.';
    }

    /**
     * The stock changes for the box at the top of the cart and checkout pages, once. The toasts
     * are marked as told too: the page says the stock changes in its box and lists the
     * unavailable lines itself.
     */
    public function takePageUnavailableNotice(): ?string
    {
        Session::forget([self::UNAVAILABLE, self::STOCK]);

        return self::joined(Session::pull(self::STOCK_PAGE, []));
    }

    private static function joined(array $messages): ?string
    {
        return $messages === [] ? null : implode(' ', $messages);
    }

    // ---- Totals ------------------------------------------------------------

    /** Hydrated cart payload for the storefront JS, cart and checkout pages. */
    /**
     * The cart as toArray() shows it, holding one sample product instead of the shopper's items:
     * for Site settings → Checkout → "View on storefront", so staff see shipping, payment and tax
     * at checkout without a bag of their own. Nothing is kept: the real cart is put back at once.
     * Null when the store has no product in stock to show.
     */
    public function preview(): ?array
    {
        $line = $this->sampleLine();
        if (! $line) {
            return null;
        }

        $real = Session::get(self::KEY);
        Session::put(self::KEY, [$this->key($line['product_id'], $line['options']) => $line]);
        try {
            return $this->toArray();
        } finally {
            $real === null ? Session::forget(self::KEY) : Session::put(self::KEY, $real);
        }
    }

    /** One in-stock product on sale (a variant product in its first active, in-stock combination). */
    private function sampleLine(): ?array
    {
        foreach (Product::query()->active()->latest('id')->limit(30)->get() as $product) {
            $options = [];
            if ($product->sellsVariants()) {
                $variant = collect($product->variantList())->first(fn ($v) => $v['is_active'] && (int) $v['stock'] > 0);
                if (! $variant) {
                    continue;
                }
                $options = $variant['options'];
            } elseif ($product->stockFor() <= 0) {
                continue;
            }

            return [
                'product_id' => $product->id,
                'options' => $options,
                'size' => $this->option($options, 'size'),
                'color' => $this->option($options, 'colour', 'color'),
                'qty' => 1,
            ];
        }

        return null;
    }

    public function toArray(): array
    {
        $raw = $this->raw();
        $products = Product::with('category')->whereIn('id', array_column($raw, 'product_id'))->get()->keyBy('id');

        $items = [];
        $subtotal = 0.0;
        $unavailable = [];
        foreach ($raw as $key => $line) {
            $product = $products[$line['product_id']] ?? null;
            // A product sold by variant is priced by the chosen combination, not by itself.
            $options = $line['options'] ?? [];
            $variant = $product?->sellsVariants() ? $product->findVariant($options) : null;
            // Hidden, deleted, or the chosen variant switched off since it was added, or sold out: the
            // line stays, listed apart and out of the totals (see describeUnavailable()).
            if (! $product || ! $product->is_active || ($product->sellsVariants() && ! $variant)) {
                $unavailable[$key] = $line + ['reason' => 'unavailable'];

                continue;
            }
            if ($product->stockFor($options) <= 0) {
                $unavailable[$key] = $line + ['reason' => 'out_of_stock'];

                continue;
            }
            $unitPrice = (float) ($variant['price'] ?? $product->price);
            $lineTotal = round($unitPrice * $line['qty'], 2);
            $subtotal += $lineTotal;
            $card = $product->toCard();
            $card['price'] = $unitPrice;
            $card['price_formatted'] = money($unitPrice);
            // The "was" price follows the chosen variant too, so the cart's strikethrough matches it.
            if ($product->sellsVariants()) {
                $pricing = Product::pricing($unitPrice, $variant['compare_at_price'] ?? null, $product->mrpBasis($unitPrice));
                $card['compare_at_price'] = $pricing['compare_at_price'];
                $card['compare_at_price_formatted'] = $pricing['compare_at_price_formatted'];
                $card['discount_percent'] = $pricing['discount_percent'];
            }
            $items[] = [
                'key' => $key,
                'qty' => $line['qty'],
                'size' => $line['size'] ?? null,
                'color' => $line['color'] ?? null,
                'options' => $options,
                'options_label' => implode(' / ', array_map(fn ($name, $value) => $name.': '.$value, array_keys($options), array_values($options))),
                'variant_sku' => $variant['sku'] ?? $product->sku,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                // The product's own tax (Catalogue → Taxes); 0% without one.
                'tax_rate' => $product->effectiveTaxRate(),
                'line_total_formatted' => money($lineTotal),
                'product' => $card,
            ];
        }

        $unavailable = $this->describeUnavailable($unavailable);

        $coupon = $this->coupon();
        $subtotal = round($subtotal, 2);
        // A code no longer met (items removed or quantities lowered since it was applied) is
        // dropped, so it neither gives free delivery nor shows as applied below its minimum.
        if ($coupon && ! $coupon->meetsMinimum($subtotal)) {
            $this->removeCoupon();
            $coupon = null;
        }
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0.0;

        $method = $this->shippingMethod();
        $freeOver = $method['free_over'];
        $shipping = $subtotal <= 0 ? 0.0 : $method['cost'];
        if (($freeOver !== null && $freeOver > 0 && $subtotal >= $freeOver) || ($coupon && $coupon->type === 'free_shipping')) {
            $shipping = 0.0;
        }

        // Each line is taxed at its product's rate, on its share of what is paid after the discount.
        // Prices that include tax already contain it (shown, not added); prices before tax get it on top.
        // Always GST until the order is placed, when the shipping state makes it IGST or CGST + SGST.
        $taxLabel = 'GST';
        $inclusive = prices_include_tax();
        $taxable = max(0, $subtotal - $discount);
        $breakdown = TaxBreakdown::for(
            array_map(fn (array $item) => ['amount' => $item['line_total'], 'rate' => $item['tax_rate']], $items),
            $discount,
            $inclusive,
        );
        foreach ($breakdown['lines'] as $i => $line) {
            $items[$i]['tax'] = $line['tax'];
        }
        $tax = $breakdown['tax'];
        // One rate when every line shares it (shown in the label); null when they differ.
        $taxRate = $items === [] ? 0.0 : $breakdown['rate'];
        $hasTax = $tax > 0 || array_filter($breakdown['rates'], fn (array $r) => $r['rate'] > 0) !== [];

        $total = round(max(0, $taxable + $shipping + ($inclusive ? 0 : $tax)), 2);

        $threshold = (float) setting('site.free_shipping_threshold', 0);
        $remaining = max(0, $threshold - $subtotal);

        $recommendations = Product::with('category')
            ->active()->bestSellers()
            ->whereNotIn('id', array_column($raw, 'product_id'))
            ->orderBy('sort_order')->limit(4)->get()
            ->map(fn (Product $p) => $p->toCard())->values();

        return [
            'items' => $items,
            // Lines no longer sold: shown greyed for the shopper to remove, never charged.
            'unavailable' => $unavailable,
            'count' => array_sum(array_column($items, 'qty')),
            'subtotal' => $subtotal,
            'subtotal_formatted' => money($subtotal),
            'coupon' => $coupon ? ['code' => $coupon->code, 'label' => $coupon->describe()] : null,
            'discount' => $discount,
            'discount_formatted' => money($discount),
            'shipping_method' => $method,
            'shipping' => $shipping,
            'shipping_formatted' => $shipping <= 0 ? 'Complimentary' : money($shipping),
            'tax' => $tax,
            'tax_rate' => $taxRate,
            'tax_label' => $taxLabel,
            'tax_inclusive' => $inclusive,
            // Per rate, for a cart with products at different rates.
            'tax_rows' => array_map(
                fn (array $row) => $row + ['line' => tax_line($row['label'], $row['rate'], $inclusive), 'amount_formatted' => money($row['amount'])],
                TaxBreakdown::rows($breakdown['rates'], $taxLabel),
            ),
            'tax_line' => tax_line($taxLabel, $taxRate, $inclusive),
            // No rate: prices that include tax say so; prices before tax simply carry no tax.
            'tax_formatted' => $hasTax || ! $inclusive ? money($tax) : 'Included',
            'total' => $total,
            'total_formatted' => money($total),
            'free_shipping_threshold' => $threshold,
            'remaining' => $remaining,
            'remaining_formatted' => money($remaining),
            'progress' => $threshold > 0 ? min(1, $subtotal / $threshold) : 1,
            'recommendations' => $recommendations,
        ];
    }

    /** One line per product and combination, so the same product in two variants stays apart. */
    private function key(int $productId, array $options): string
    {
        ksort($options);

        return substr(md5($productId.'|'.json_encode($options)), 0, 12);
    }
}
