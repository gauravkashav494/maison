<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
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

    public function coupon(): ?Coupon
    {
        $code = Session::get(self::COUPON);

        return $code ? Coupon::findValid($code) : null;
    }

    // ---- Shipping ----------------------------------------------------------

    /** @return array<int, array{code:string,name:string,description:?string,cost:int,free_over:?int,eta:?string}> */
    public function shippingMethods(): array
    {
        $methods = setting('checkout.shipping_methods', []);
        if (empty($methods)) {
            $methods = [['code' => 'standard', 'name' => 'Standard delivery', 'description' => 'Tracked, 3–5 business days', 'cost' => 250, 'free_over' => (int) setting('site.free_shipping_threshold', 0), 'eta' => '3–5 business days']];
        }

        return array_map(fn ($m) => [
            'code' => $m['code'] ?? str($m['name'])->slug()->toString(),
            'name' => $m['name'] ?? 'Delivery',
            'description' => $m['description'] ?? null,
            'cost' => (int) ($m['cost'] ?? 0),
            'free_over' => isset($m['free_over']) && $m['free_over'] !== '' ? (int) $m['free_over'] : null,
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

    // ---- Totals ------------------------------------------------------------

    /** Hydrated cart payload for the storefront JS, cart and checkout pages. */
    public function toArray(): array
    {
        $raw = $this->raw();
        $products = Product::with('category')->whereIn('id', array_column($raw, 'product_id'))->get()->keyBy('id');

        $items = [];
        $subtotal = 0;
        foreach ($raw as $key => $line) {
            $product = $products[$line['product_id']] ?? null;
            if (! $product || ! $product->is_active) {
                continue;
            }
            // A product sold by variant is priced by the chosen combination, not by itself.
            $options = $line['options'] ?? [];
            $variant = $product->sellsVariants() ? $product->findVariant($options) : null;
            $unitPrice = $variant['price'] ?? $product->price;
            $lineTotal = $unitPrice * $line['qty'];
            $subtotal += $lineTotal;
            $card = $product->toCard();
            $card['price'] = $unitPrice;
            $card['price_formatted'] = money($unitPrice);
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
                'line_total_formatted' => money($lineTotal),
                'product' => $card,
            ];
        }

        $coupon = $this->coupon();
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;

        $method = $this->shippingMethod();
        $freeOver = $method['free_over'];
        $shipping = $subtotal === 0 ? 0 : $method['cost'];
        if (($freeOver !== null && $freeOver > 0 && $subtotal >= $freeOver) || ($coupon && $coupon->type === 'free_shipping')) {
            $shipping = 0;
        }

        $taxRate = (float) setting('checkout.tax_rate', 0);
        $taxable = max(0, $subtotal - $discount);
        $tax = $taxRate > 0 ? (int) round($taxable * $taxRate / 100) : 0;

        $total = max(0, $taxable + $shipping + $tax);

        $threshold = (int) setting('site.free_shipping_threshold', 0);
        $remaining = max(0, $threshold - $subtotal);

        $recommendations = Product::with('category')
            ->active()->bestSellers()
            ->whereNotIn('id', array_column($raw, 'product_id'))
            ->orderBy('sort_order')->limit(4)->get()
            ->map(fn (Product $p) => $p->toCard())->values();

        return [
            'items' => $items,
            'count' => array_sum(array_column($items, 'qty')),
            'subtotal' => $subtotal,
            'subtotal_formatted' => money($subtotal),
            'coupon' => $coupon ? ['code' => $coupon->code, 'label' => $coupon->describe()] : null,
            'discount' => $discount,
            'discount_formatted' => money($discount),
            'shipping_method' => $method,
            'shipping' => $shipping,
            'shipping_formatted' => $shipping === 0 ? 'Complimentary' : money($shipping),
            'tax' => $tax,
            'tax_rate' => $taxRate,
            'tax_label' => setting('checkout.tax_label', 'GST'),
            'tax_formatted' => $taxRate > 0 ? money($tax) : 'Included',
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
