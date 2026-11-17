@extends('layouts.app')

{{--
    Checkout: three numbered steps (contact & delivery address, delivery method, payment) beside
    a summary of the order. Field names and the Alpine `checkout` component are the shared
    ones, so the controller receives exactly the request every template sends.
--}}

@php
    $field = fn (string $name) => $errors->has($name) ? 'border-[var(--bk-out)]' : '';
@endphp

@section('content')
<div class="bk-wrap mt-4"
     x-data="checkout({
        shipping: @js(old('shipping_method', $cart['shipping_method']['code'])),
        payment: @js(old('payment_method', array_key_first($paymentMethods))),
        codFee: {{ $codFee }},
     })">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Checkout</h1>
        <a href="{{ route('cart') }}" class="bk-link text-sm">← Back to your order</a>
    </div>

    <form method="post" action="{{ route('checkout.place') }}" class="checkout-form mt-4 grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_24rem]" @submit="submitting = true" novalidate>
        @csrf

        <div class="space-y-3">
            <section class="bk-card p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h2 class="flex items-center gap-3 text-lg font-extrabold"><span class="bk-step-no">1</span>Contact &amp; delivery address</h2>
                    @guest
                        <p class="bk-muted text-sm"><a href="{{ route('login') }}?redirect=checkout" class="bk-link">Sign in</a> to use your saved address</p>
                    @endguest
                </div>
                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2.5 sm:grid-cols-12">
                    <x-checked-field name="shipping_name" label="Name or business" rule="name" attribute="full name" :value="$address?->name ?? $user?->name" required autocomplete="name" class="col-span-2 sm:col-span-4" />
                    <x-phone-field :value="$user?->phone ?? $address?->phone" required class="col-span-2 sm:col-span-4" />
                    <x-checked-field name="email" label="Email" rule="email" attribute="email address" :value="$user?->email" required type="email" autocomplete="email" class="col-span-2 sm:col-span-4" />
                    <x-checked-field name="shipping_line1" label="Address" rule="line" attribute="address" :value="$address?->line1" required autocomplete="address-line1" placeholder="Unit / building, street" class="col-span-2 sm:col-span-8" />
                    <x-checked-field name="shipping_line2" label="Area, landmark" rule="line" attribute="apartment or landmark" :value="$address?->line2" autocomplete="address-line2" class="col-span-2 sm:col-span-4" />
                    <x-checked-field name="shipping_city" label="City" rule="place" attribute="city" :value="$address?->city" required autocomplete="address-level2" class="sm:col-span-3" />
                    <x-checked-field name="shipping_state" label="State" rule="place" attribute="state" :value="$address?->state" required autocomplete="address-level1" class="sm:col-span-3" />
                    <x-checked-field name="shipping_postal_code" label="PIN code" rule="postal" attribute="postal code" :value="$address?->postal_code" required autocomplete="postal-code" inputmode="numeric" class="sm:col-span-3" />
                    <x-checked-field name="shipping_country" label="Country" rule="place" attribute="country" :value="$address?->country ?? 'India'" required autocomplete="country-name" class="sm:col-span-3" />
                </div>
                @auth
                    <label class="mt-3 flex items-center gap-2 text-sm">
                        <input type="checkbox" name="save_address" value="1" checked class="accent-[var(--bk-brand)]"> Save this address for next time
                    </label>
                @endauth
            </section>

            <div class="bk-card overflow-hidden">
            <section>
                <h2 class="flex items-center gap-3 p-4 pb-3 text-lg font-extrabold sm:px-5"><span class="bk-step-no">2</span>Delivery</h2>
                @error('shipping_method')<p class="bk-error px-4 sm:px-5">{{ $message }}</p>@enderror
                <div class="grid border-t border-[var(--bk-line)] sm:grid-cols-2 sm:[&>.bk-option:nth-child(2)]:border-t-0 sm:[&>.bk-option:nth-child(even)]:border-l sm:[&>.bk-option:nth-child(even)]:border-[var(--bk-line)]">
                    @foreach($shippingMethods as $m)
                        @php $free = $m['free_over'] !== null && $m['free_over'] > 0 && $cart['subtotal'] >= $m['free_over']; @endphp
                        <label class="bk-option" :class="shipping === @js($m['code']) && 'is-on'">
                            <input type="radio" name="shipping_method" value="{{ $m['code'] }}"
                                   :checked="shipping === @js($m['code'])" @change="setShipping(@js($m['code']))">
                            <span class="flex flex-1 justify-between gap-4">
                                <span><span class="font-bold">{{ $m['name'] }}</span>
                                    <span class="bk-muted block text-xs">{{ $m['description'] }}@if($m['eta']) · {{ $m['eta'] }}@endif</span></span>
                                <span class="font-bold bk-num">{{ ($free || $m['cost'] <= 0) ? 'Free' : money($m['cost']) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="border-t border-[var(--bk-line)]">
                <h2 class="flex items-center gap-3 p-4 pb-3 text-lg font-extrabold sm:px-5"><span class="bk-step-no">3</span>Payment</h2>
                <div class="border-t border-[var(--bk-line)]">
                    @foreach($paymentMethods as $code => $name)
                        <label class="bk-option" :class="payment === @js($code) && 'is-on'">
                            <input type="radio" name="payment_method" value="{{ $code }}" x-model="payment">
                            <span class="flex flex-1 justify-between gap-4">
                                <span class="font-bold">{{ $name }}@if($code === 'cod')<span class="bk-muted block text-xs font-normal">Pay the driver on delivery</span>@endif</span>
                                @if($code === 'cod')<span class="bk-muted text-sm">+ {{ money($codFee) }} fee</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('payment_method')<p class="bk-error px-4 pb-4 sm:px-5">{{ $message }}</p>@enderror
                @if(array_diff(array_keys($paymentMethods), ['cod']))<p class="bk-muted px-4 pb-4 text-xs sm:px-5">Payment is taken after the order is placed. No card details are stored.</p>@endif
            </section>
            </div>

            <div x-data="{ open: @js(filled(old('notes')) || $errors->has('notes')) }">
                <button type="button" class="bk-link text-sm" @click="open = !open" :aria-expanded="open"><span x-text="open ? '−' : '+'">+</span> Add a purchase order number or delivery notes</button>
                <label x-show="open" x-collapse x-cloak class="bk-label bk-card mt-2 block p-4 sm:p-5"><span class="sr-only">Purchase order number or delivery notes</span>
                    <textarea name="notes" rows="2" class="bk-textarea" placeholder="e.g. PO-2291 · deliver to the back gate before 10 am">{{ old('notes') }}</textarea>
                    @error('notes')<span class="bk-error">{{ $message }}</span>@enderror
                </label>
            </div>
        </div>

        <aside class="bk-card bk-summary p-5">
            <p class="text-lg font-extrabold">Order summary</p>
            <ul class="mt-3 max-h-80 divide-y divide-[var(--bk-line)] overflow-y-auto">
                @foreach($cart['items'] as $item)
                    <li class="flex justify-between gap-3 py-2.5 text-sm">
                        <span class="min-w-0">
                            <span class="block font-bold leading-snug">{{ $item['product']['name'] }}</span>
                            <span class="bk-code">{{ $item['variant_sku'] ?: ($item['product']['sku'] ?? '') }}@if($item['options_label'] ?? null) · {{ $item['options_label'] }}@endif · × {{ $item['qty'] }}</span>
                        </span>
                        <span class="font-bold bk-num">{{ $item['line_total_formatted'] }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- Read from the cart store so a delivery or payment change updates the totals at once. --}}
            <dl class="bk-totals mt-4">
                <dt>Subtotal</dt><dd x-text="$store.cart.subtotal_formatted || @js($cart['subtotal_formatted'])"></dd>
                <template x-if="$store.cart.discount > 0"><dt>Discount</dt></template>
                <template x-if="$store.cart.discount > 0"><dd x-text="'− ' + $store.cart.discount_formatted"></dd></template>
                <dt>Delivery</dt><dd x-text="$store.cart.shipping_formatted || @js($cart['shipping_formatted'])"></dd>
                <template x-if="codExtra > 0"><dt>Cash on delivery fee</dt></template>
                <template x-if="codExtra > 0"><dd x-text="fmt(codExtra)"></dd></template>
                @if(shows_tax_row())
                    <dt x-text="$store.cart.tax_line || @js($cart['tax_line'])"></dt>
                    <dd x-text="$store.cart.tax_formatted || @js($cart['tax_formatted'])"></dd>
                @endif
                <dt class="is-total">Total</dt><dd class="is-total" x-text="fmt(($store.cart.total ?? {{ $cart['total'] }}) + codExtra)"></dd>
            </dl>
            @if(prices_include_tax())<p class="mt-1 text-right text-xs bk-muted">Inclusive of all taxes</p>@endif

            <button type="submit" class="bk-btn bk-btn-primary bk-btn-block mt-4 !py-3 text-base" :disabled="submitting">
                <x-heroicon-o-check-circle aria-hidden="true" /><span x-text="submitting ? 'Placing order…' : 'Place order'">Place order</span>
            </button>
            <p class="bk-muted mt-3 text-center text-xs">By placing this order you agree to our <a href="/terms" class="bk-link">terms</a> and <a href="/privacy" class="bk-link">privacy policy</a>.</p>
        </aside>
    </form>
</div>
@endsection
