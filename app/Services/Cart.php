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

    /** @return array<string, array{product_id:int,size:string,color:?string,qty:int}> */
    public function raw(): array
    {
        return Session::get(self::KEY, []);
    }

    public function add(int $productId, string $size, ?string $color, int $qty = 1): void
    {
        $items = $this->raw();
        $key = $this->key($productId, $size, $color);
        $items[$key] = [
            'product_id' => $productId,
            'size' => $size,
            'color' => $color,
            'qty' => ($items[$key]['qty'] ?? 0) + max(1, $qty),
        ];
        $items = [$key => $items[$key]] + $items; // most recently added first
        Session::put(self::KEY, $items);
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
            $lineTotal = $product->price * $line['qty'];
            $subtotal += $lineTotal;
            $items[] = [
                'key' => $key,
                'qty' => $line['qty'],
                'size' => $line['size'],
                'color' => $line['color'],
                'line_total' => $lineTotal,
                'line_total_formatted' => money($lineTotal),
                'product' => $product->toCard(),
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

    private function key(int $productId, string $size, ?string $color): string
    {
        return substr(md5($productId.'|'.$size.'|'.($color ?? '')), 0, 12);
    }
}
