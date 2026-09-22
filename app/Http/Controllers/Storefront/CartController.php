<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscriber;
use App\Services\Cart;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly Cart $cart) {}

    public function page(): View
    {
        return view('shop.cart', ['cart' => $this->cart->toArray(), 'seo' => Seo::simple('Shopping Bag', null, noindex: true)]);
    }

    // ---- JSON API used by the Alpine cart store ---------------------------

    public function items(): JsonResponse
    {
        return response()->json($this->cart->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'size' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:40'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $product = Product::active()->findOrFail($data['product_id']);
        abort_unless(in_array($data['size'], $product->sizes ?? [], true), 422, 'Invalid size.');

        $this->cart->add($product->id, $data['size'], $data['color'] ?? null, (int) ($data['qty'] ?? 1));

        return response()->json($this->cart->toArray());
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:20']]);
        $this->cart->update($key, (int) $data['qty']);

        return response()->json($this->cart->toArray());
    }

    public function destroy(string $key): JsonResponse
    {
        $this->cart->remove($key);

        return response()->json($this->cart->toArray());
    }

    public function coupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $coupon = $this->cart->applyCoupon($data['code']);
        if (! $coupon) {
            return response()->json(['message' => 'That code is not valid or has expired.'], 422);
        }
        if ($coupon->discountFor($this->cart->toArray()['subtotal']) === 0 && $coupon->type !== 'free_shipping') {
            $this->cart->removeCoupon();

            return response()->json(['message' => 'This code needs a minimum subtotal of '.money($coupon->min_subtotal).'.'], 422);
        }

        return response()->json($this->cart->toArray());
    }

    public function removeCoupon(): JsonResponse
    {
        $this->cart->removeCoupon();

        return response()->json($this->cart->toArray());
    }

    public function shipping(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $this->cart->setShippingMethod($data['code']);

        return response()->json($this->cart->toArray());
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'source' => ['nullable', 'string', 'max:60']]);
        Subscriber::firstOrCreate(['email' => strtolower($data['email'])], ['source' => $data['source'] ?? 'site', 'template' => template()->id()]);

        return response()->json(['ok' => true]);
    }

    // ---- Checkout ----------------------------------------------------------

    public function checkout(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->toArray();
        if (empty($cart['items'])) {
            return redirect()->route('cart');
        }

        $user = $request->user();
        $default = $user?->addresses()->where('is_default', true)->first() ?? $user?->addresses()->first();

        $enabled = setting('checkout.payment_methods', array_keys(Order::PAYMENT_METHODS));
        $payments = array_filter(Order::PAYMENT_METHODS, fn ($k) => in_array($k, (array) $enabled, true), ARRAY_FILTER_USE_KEY);

        return view('shop.checkout', [
            'cart' => $cart,
            'shippingMethods' => $this->cart->shippingMethods(),
            'paymentMethods' => $payments,
            'codFee' => (int) setting('checkout.cod_fee', 0),
            'user' => $user,
            'address' => $default,
            'seo' => Seo::simple('Checkout', null, noindex: true),
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $cart = $this->cart->toArray();
        if (empty($cart['items'])) {
            return redirect()->route('cart');
        }

        $enabled = (array) setting('checkout.payment_methods', array_keys(Order::PAYMENT_METHODS));
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'shipping_name' => ['required', 'string', 'max:120'],
            'shipping_line1' => ['required', 'string', 'max:190'],
            'shipping_line2' => ['nullable', 'string', 'max:190'],
            'shipping_city' => ['required', 'string', 'max:80'],
            'shipping_state' => ['required', 'string', 'max:80'],
            'shipping_postal_code' => ['required', 'string', 'max:20'],
            'shipping_country' => ['required', 'string', 'max:80'],
            'shipping_method' => ['required', 'string'],
            'payment_method' => ['required', 'string', 'in:'.implode(',', $enabled)],
            'notes' => ['nullable', 'string', 'max:500'],
            'save_address' => ['nullable', 'boolean'],
        ]);

        $this->cart->setShippingMethod($data['shipping_method']);
        $cart = $this->cart->toArray();
        $codFee = $data['payment_method'] === 'cod' ? (int) setting('checkout.cod_fee', 0) : 0;

        $order = DB::transaction(function () use ($data, $cart, $codFee, $request) {
            $order = Order::create([
                'number' => Order::generateNumber(),
                'template' => template()->id(),
                'user_id' => $request->user()?->id,
                'status' => 'confirmed',
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'shipping_name' => $data['shipping_name'],
                'shipping_line1' => $data['shipping_line1'],
                'shipping_line2' => $data['shipping_line2'] ?? null,
                'shipping_city' => $data['shipping_city'],
                'shipping_state' => $data['shipping_state'],
                'shipping_postal_code' => $data['shipping_postal_code'],
                'shipping_country' => $data['shipping_country'],
                'shipping_method' => $cart['shipping_method']['name'],
                'shipping_cost' => $cart['shipping'] + $codFee,
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_method'] === 'cod' ? 'cod' : 'paid',
                'subtotal' => $cart['subtotal'],
                'discount' => $cart['discount'],
                'coupon_code' => $cart['coupon']['code'] ?? null,
                'tax' => $cart['tax'],
                'total' => $cart['total'] + $codFee,
                'estimated_delivery' => now()->addDays(5),
                'notes' => $data['notes'] ?? null,
                'status_history' => [['status' => 'confirmed', 'at' => now()->toIso8601String(), 'note' => 'Order placed']],
            ]);

            foreach ($cart['items'] as $line) {
                $order->items()->create([
                    'product_id' => $line['product']['id'],
                    'name' => $line['product']['name'],
                    'sku' => Product::find($line['product']['id'])?->sku,
                    'image' => $line['product']['images'][0] ?? null,
                    'size' => $line['size'],
                    'color' => $line['color'],
                    'price' => $line['product']['price'],
                    'qty' => $line['qty'],
                    'total' => $line['line_total'],
                ]);
                Product::whereKey($line['product']['id'])->where('stock', '>=', $line['qty'])->decrement('stock', $line['qty']);
            }

            if (! empty($cart['coupon']['code'])) {
                Coupon::where('code', $cart['coupon']['code'])->increment('used_count');
            }

            if ($request->user() && $request->boolean('save_address')) {
                $request->user()->addresses()->create([
                    'name' => $data['shipping_name'],
                    'phone' => $data['phone'],
                    'line1' => $data['shipping_line1'],
                    'line2' => $data['shipping_line2'] ?? null,
                    'city' => $data['shipping_city'],
                    'state' => $data['shipping_state'],
                    'postal_code' => $data['shipping_postal_code'],
                    'country' => $data['shipping_country'],
                    'is_default' => $request->user()->addresses()->count() === 0,
                ]);
            }

            return $order;
        });

        $this->cart->clear();
        session(['last_order_number' => $order->number]);

        return redirect()->route('orders.confirmation', $order->number);
    }

    public function confirmation(Request $request, string $number): View
    {
        $order = Order::with('items')->where('number', $number)->firstOrFail();
        // Guests may only see the order they just placed; customers may see their own.
        abort_unless(
            session('last_order_number') === $order->number || ($request->user() && $order->user_id === $request->user()->id),
            403,
        );

        return view('shop.confirmation', ['order' => $order, 'seo' => Seo::simple('Order confirmed', null, noindex: true)]);
    }
}
