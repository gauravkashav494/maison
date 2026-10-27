@extends('layouts.app')

{{--
    Checkout for the Simple List template.

    The shared checkout opens with a full-width photograph and shows a thumbnail per line,
    which is wrong for a storefront that carries no imagery — an image-free product rendered
    there is a broken picture box. The fields, their names and the Alpine `checkout` component
    are the same as the shared view, so the controller sees exactly the same request; only the
    presentation differs: a plain two-column form with the order listed beside it.
--}}

@section('content')
<div class="ol-page"
     x-data="checkout({
        shipping: @js(old('shipping_method', $cart['shipping_method']['code'])),
        payment: @js(old('payment_method', array_key_first($paymentMethods))),
        codFee: {{ $codFee }},
     })">

    <div class="ol-head">
        <h1>Checkout</h1>
        <p><a href="{{ route('cart') }}">Back to your order</a></p>
    </div>

    @if($errors->any())
        <p class="ol-note" style="color:var(--ol-out)">Please check the highlighted fields below.</p>
    @endif

    <form method="post" action="{{ route('checkout.place') }}" class="ol-form" @submit="submitting = true">
        @csrf

        <div class="ol-steps">
            <fieldset class="ol-fieldset">
                <legend>1 · Contact</legend>
                @guest
                    <p class="ol-note" style="margin-top:.35rem">Have an account?
                        <a href="{{ route('login') }}?redirect=checkout">Sign in</a> for a faster checkout.</p>
                @endguest
                <div class="ol-grid">
                    <label class="ol-field">Email
                        <input name="email" type="email" required autocomplete="email" value="{{ old('email', $user?->email) }}">
                        @error('email')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field">Phone
                        <input name="phone" type="tel" required autocomplete="tel" value="{{ old('phone', $user?->phone ?? $address?->phone) }}">
                        @error('phone')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                </div>
            </fieldset>

            <fieldset class="ol-fieldset">
                <legend>2 · Delivery address</legend>
                <div class="ol-grid">
                    <label class="ol-field is-wide">Full name
                        <input name="shipping_name" required autocomplete="name" value="{{ old('shipping_name', $address?->name ?? $user?->name) }}">
                        @error('shipping_name')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field is-wide">Address
                        <input name="shipping_line1" required autocomplete="address-line1" placeholder="House / flat, street" value="{{ old('shipping_line1', $address?->line1) }}">
                        @error('shipping_line1')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field is-wide">Apartment, landmark (optional)
                        <input name="shipping_line2" autocomplete="address-line2" value="{{ old('shipping_line2', $address?->line2) }}">
                    </label>
                    <label class="ol-field">City
                        <input name="shipping_city" required autocomplete="address-level2" value="{{ old('shipping_city', $address?->city) }}">
                        @error('shipping_city')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field">State
                        <input name="shipping_state" required autocomplete="address-level1" value="{{ old('shipping_state', $address?->state) }}">
                        @error('shipping_state')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field">Postal code
                        <input name="shipping_postal_code" required autocomplete="postal-code" inputmode="numeric" value="{{ old('shipping_postal_code', $address?->postal_code) }}">
                        @error('shipping_postal_code')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field">Country
                        <input name="shipping_country" required autocomplete="country-name" value="{{ old('shipping_country', $address?->country ?? 'India') }}">
                    </label>
                </div>
                @auth
                    <label class="ol-note" style="display:flex;gap:.5rem;align-items:center;margin-top:.75rem">
                        <input type="checkbox" name="save_address" value="1" checked> Save this address to my account
                    </label>
                @endauth
            </fieldset>

            <fieldset class="ol-fieldset">
                <legend>3 · Delivery method</legend>
                <div class="ol-options">
                    @foreach($shippingMethods as $m)
                        @php $free = $m['free_over'] !== null && $m['free_over'] > 0 && $cart['subtotal'] >= $m['free_over']; @endphp
                        <label class="ol-option" :class="shipping === @js($m['code']) && 'is-on'">
                            <input type="radio" name="shipping_method" value="{{ $m['code'] }}"
                                   :checked="shipping === @js($m['code'])" @change="setShipping(@js($m['code']))">
                            <span class="ol-option-main">
                                <span>{{ $m['name'] }}<span class="ol-sub">{{ $m['description'] }}@if($m['eta']) · {{ $m['eta'] }}@endif</span></span>
                                <span class="ol-price">{{ ($free || $m['cost'] === 0) ? 'Free' : money($m['cost']) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="ol-fieldset">
                <legend>4 · Payment</legend>
                <div class="ol-options">
                    @foreach($paymentMethods as $code => $name)
                        <label class="ol-option" :class="payment === @js($code) && 'is-on'">
                            <input type="radio" name="payment_method" value="{{ $code }}" x-model="payment">
                            <span class="ol-option-main">
                                <span>{{ $name }}</span>
                                @if($code === 'cod')<span class="ol-price">+ {{ money($codFee) }} fee</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
                {{-- Card details are captured by the gateway, so nothing is asked for here. --}}
                <p class="ol-note">Payment is taken after the order is placed. No card details are stored.</p>
                @error('payment_method')<span class="ol-error">{{ $message }}</span>@enderror
            </fieldset>

            <label class="ol-field">Order notes (optional)
                <textarea name="notes" rows="2" placeholder="Delivery instructions, purchase order number…">{{ old('notes') }}</textarea>
            </label>
        </div>

        <aside class="ol-summary">
            <h2>Your order</h2>

            <table class="ol-table">
                <tbody>
                    @foreach($cart['items'] as $item)
                        <tr>
                            <td class="ol-name">
                                {{ $item['product']['name'] }}
                                <span class="ol-sub">
                                    {{ $item['variant_sku'] ?: ($item['product']['sku'] ?? '') }}
                                    @if($item['options_label'] ?? null) · {{ $item['options_label'] }} @endif
                                    · Qty {{ $item['qty'] }}
                                </span>
                            </td>
                            <td class="ol-price">{{ $item['line_total_formatted'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Read from the cart store so the totals follow a delivery or payment change
                 without a page load, falling back to the server values before it loads. --}}
            <dl class="ol-detail" style="gap:.4rem 1.25rem;margin-top:1rem">
                <dt>Subtotal</dt>
                <dd x-text="$store.cart.subtotal_formatted || @js($cart['subtotal_formatted'])"></dd>

                <template x-if="$store.cart.discount > 0"><dt>Discount</dt></template>
                <template x-if="$store.cart.discount > 0"><dd x-text="'− ' + $store.cart.discount_formatted"></dd></template>

                <dt>Delivery</dt>
                <dd x-text="$store.cart.shipping_formatted || @js($cart['shipping_formatted'])"></dd>

                <template x-if="codExtra > 0"><dt>Cash on delivery fee</dt></template>
                <template x-if="codExtra > 0"><dd x-text="fmt(codExtra)"></dd></template>

                <dt x-text="'Tax (' + ($store.cart.tax_label || @js($cart['tax_label'])) + ')'"></dt>
                <dd x-text="$store.cart.tax_formatted || @js($cart['tax_formatted'])"></dd>

                <dt><strong>Total</strong></dt>
                <dd><strong x-text="fmt(($store.cart.total ?? {{ $cart['total'] }}) + codExtra)"></strong></dd>
            </dl>

            <button type="submit" class="ol-add" style="width:100%;margin-top:1rem" :disabled="submitting"
                    x-text="submitting ? 'Placing order…' : 'Place order'">Place order</button>

            <p class="ol-note">By placing this order you agree to our
                <a href="/terms">terms</a> and <a href="/privacy">privacy policy</a>.</p>
        </aside>
    </form>
</div>
@endsection
