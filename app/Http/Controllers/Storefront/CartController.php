<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Http\Requests\Storefront\CodeRequest;
use App\Http\Requests\Storefront\NewsletterRequest;
use App\Http\Requests\Storefront\PincodeRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscriber;
use App\Services\Cart;
use App\Services\PlaceOrder;
use App\Support\CustomerOtp;
use App\Support\SalesOrder;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public const REMOVE_UNAVAILABLE = 'Remove the items that are no longer available from your cart to place your order.';

    public function __construct(private readonly Cart $cart) {}

    public function page(): View
    {
        return view('shop.cart', [
            'cart' => $this->cart->toArray(),
            'unavailableNotice' => $this->cart->takePageUnavailableNotice(),
            'seo' => Seo::simple('Cart', null, noindex: true),
        ]);
    }

    // ---- JSON API used by the Alpine cart store ---------------------------

    /**
     * The cart for the Alpine store, with `unavailable_notice` set once when lines were taken out
     * because the product was hidden or deleted (shown as a toast by every template).
     */
    private function cartJson(): JsonResponse
    {
        $cart = $this->cart->toArray();

        return response()->json($cart + [
            'unavailable_notice' => $this->cart->takeUnavailableNotice(),
            'unavailable_toast' => $this->cart->takeUnavailableToast(),
        ]);
    }

    public function items(): JsonResponse
    {
        return $this->cartJson();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:60'],
            'size' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:40'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);
        $product = Product::active()->findOrFail($data['product_id']);

        // Size and colour are the older form fields; they are read as two of the attributes.
        $posted = (array) ($data['options'] ?? []);
        filled($data['size'] ?? null) and $posted['Size'] ??= $data['size'];
        filled($data['color'] ?? null) and $posted['Colour'] ??= $data['color'];

        // Only values this product actually offers survive, so nothing arbitrary reaches the order.
        $options = $product->normaliseOptions($posted);
        $missing = array_diff($product->variantAttributeNames(), array_keys($options));
        abort_if($missing !== [], 422, 'Please choose '.implode(', ', $missing).'.');

        if ($product->sellsVariants()) {
            $variant = $product->findVariant($options);
            abort_unless((bool) $variant, 422, 'That variant is not available.');
            abort_unless($variant['stock'] > 0, 422, 'That variant is out of stock.');
        }

        // No more than the product's "max quantity per order" or the stock of the choice.
        $qty = (int) ($data['qty'] ?? 1);
        $inCart = $this->cart->quantityOf($product->id, $options);
        $refused = $product->orderLimitMessage($inCart + $qty, $options, $inCart);
        abort_if($refused !== null, 422, $refused);

        $this->cart->add($product->id, $options, $qty);

        return $this->cartJson();
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0']]);
        $line = $this->cart->raw()[$key] ?? null;
        if ($line && (int) $data['qty'] > (int) $line['qty'] && ($product = Product::find($line['product_id']))) {
            $refused = $product->orderLimitMessage((int) $data['qty'], (array) $line['options']);
            abort_if($refused !== null, 422, $refused);
        }
        $this->cart->update($key, (int) $data['qty']);

        return $this->cartJson();
    }

    public function destroy(string $key): JsonResponse
    {
        $this->cart->remove($key);

        return $this->cartJson();
    }

    public function coupon(CodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $coupon = $this->cart->applyCoupon($data['code']);
        if (! $coupon) {
            return response()->json(['message' => 'That code is not valid or has expired.'], 422);
        }
        $subtotal = $this->cart->toArray()['subtotal'];
        if (! $coupon->meetsMinimum($subtotal) || ($coupon->discountFor($subtotal) <= 0 && $coupon->type !== 'free_shipping')) {
            $this->cart->removeCoupon();

            return response()->json(['message' => 'This code needs a minimum subtotal of '.money($coupon->min_subtotal).'.'], 422);
        }

        return $this->cartJson();
    }

    public function removeCoupon(): JsonResponse
    {
        $this->cart->removeCoupon();

        return $this->cartJson();
    }

    public function shipping(CodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->cart->setShippingMethod($data['code']);

        return $this->cartJson();
    }

    public function subscribe(NewsletterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $subscriber = Subscriber::withTrashed()->firstOrCreate(['email' => strtolower($data['email'])], ['source' => $data['source'] ?? 'site', 'template' => template()->id()]);
        if ($subscriber->trashed()) {
            $subscriber->restore(); // re-subscribing after being removed brings the same row back
        }
        if ($subscriber->isUnsubscribed()) {
            $subscriber->forceFill(['unsubscribed_at' => null])->save(); // signing up again is a fresh opt-in
        }

        return response()->json(['ok' => true]);
    }

    /** Checks the delivery PIN code a shopper enters in the location sheet. */
    public function pincode(PincodeRequest $request): JsonResponse
    {
        return response()->json(['pincode' => $request->validated('pincode')]);
    }

    // ---- Checkout ----------------------------------------------------------

    public function checkout(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->toArray();
        if (empty($cart['items'])) {
            return redirect()->route('cart');
        }
        // An item taken off or lowered just now (here or when an order was tried) is said on this page.
        $unavailableNotice = $this->cart->takePageUnavailableNotice();

        $user = $request->user();
        $default = $user?->addresses()->where('is_default', true)->first() ?? $user?->addresses()->first();

        $payments = Order::checkoutPaymentMethods();
        $salesOrder = SalesOrder::active();

        return view('shop.checkout', [
            'cart' => $cart,
            'shippingMethods' => $this->cart->shippingMethods(),
            'paymentMethods' => $payments,
            'codFee' => $salesOrder ? 0.0 : (float) setting('checkout.cod_fee', 0),
            'user' => $user,
            'address' => $default,
            'unavailableNotice' => $unavailableNotice,
            // A sales person: who the order is for instead of an address and a delivery method.
            'salesOrder' => $salesOrder,
            'seo' => Seo::simple('Checkout', null, noindex: true),
        ]);
    }

    /** Private, client-scoped lookup used to fill a known customer's name for a sales order. */
    public function salesCustomerLookup(Request $request): JsonResponse
    {
        $salesperson = SalesOrder::salesperson();
        abort_unless($salesperson, 404);

        $data = $request->validate(['phone' => ['required', 'string', 'regex:/^\d{10}$/']]);
        $customer = SalesOrder::existingCustomerByPhone((int) $salesperson->client_id, $data['phone']);

        return response()->json(['name' => $customer?->name, 'email' => $customer?->email]);
    }

    public function placeOrder(CheckoutRequest $request, PlaceOrder $placeOrder, CustomerOtp $otp): RedirectResponse
    {
        if (empty($this->cart->toArray()['items'])) {
            return redirect()->route('cart');
        }
        // Items no longer sold stay in the cart until the shopper removes them: no order until then.
        if ($this->cart->hasUnavailableItems()) {
            return redirect()->route('checkout')->withInput()->withErrors(['cart' => self::REMOVE_UNAVAILABLE]);
        }
        // Lines lowered for stock since the checkout page opened: no order until the shopper has seen
        // what is left. The page says what changed.
        if ($this->cart->hasUnavailableNotice()) {
            return redirect()->route('checkout')->withInput();
        }

        $data = $request->validated();

        // A shopper without an account proves the phone number with a WhatsApp code first. Once it
        // is entered, an account is made from these details and the order is placed (OtpController).
        if (! $request->user()) {
            if ($error = $otp->start(CustomerOtp::CHECKOUT, $data['phone'], $data)) {
                return back()->withInput()->withErrors(['phone' => $error]);
            }

            return redirect()->route('otp.verify');
        }

        // A sales person: no address (the store's is used), for themselves or a customer they serve.
        $customer = $request->user();
        if ($salesperson = SalesOrder::salesperson()) {
            [$data, $customer] = SalesOrder::prepare($data, $salesperson);
        }

        try {
            $order = $placeOrder($data, $customer, ! $salesperson && $request->boolean('save_address'));
        } catch (InsufficientStockException) {
            // Bought by someone else (or in another window) meanwhile: the cart now holds what is
            // left, and the checkout page says what changed before the shopper places it again.
            return redirect()->route('checkout')->withInput();
        }
        session(['last_order_number' => $order->number]);

        return redirect()->route('orders.confirmation', $order->number);
    }

    public function confirmation(Request $request, string $number): View
    {
        $order = Order::with('items')->where('number', $number)->firstOrFail();
        // Guests may only see the order they just placed; customers may see their own.
        abort_unless(
            session('last_order_number') === $order->number || ($request->user() && (int) $order->customer_id === (int) $request->user()->id),
            403,
        );

        return view('shop.confirmation', ['order' => $order, 'seo' => Seo::simple('Order confirmed', null, noindex: true)]);
    }
}
