@extends('layouts.app')

{{--
    Checkout: four numbered steps (contact, delivery address, delivery method, payment) beside
    a summary of the order. Field names and the Alpine `checkout` component are the shared
    ones, so the controller receives exactly the request every template sends.
--}}

@php
    $field = fn (string $name) => $errors->has($name) ? 'border-[var(--bk-out)]' : '';
@endphp

@section('content')
<div class="bk-wrap mt-6"
     x-data="checkout({
        shipping: @js(old('shipping_method', $cart['shipping_method']['code'])),
        payment: @js(old('payment_method', array_key_first($paymentMethods))),
        codFee: {{ $codFee }},
     })">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Checkout</h1>
        <a href="{{ route('cart') }}" class="bk-link text-sm">← Back to your order</a>
    </div>

    <form method="post" action="{{ route('checkout.place') }}" class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]" @submit="submitting = true" novalidate>
        @csrf

        <div class="space-y-5">
            <section class="bk-card p-5 sm:p-6">
                <h2 class="flex items-center gap-3 text-lg font-extrabold"><span class="bk-step-no">1</span>Contact</h2>
                @guest
                    <p class="bk-muted mt-2 text-sm">Ordering regularly? <a href="{{ route('login') }}?redirect=checkout" class="bk-link">Sign in</a> to use your saved address and repeat orders.</p>
                @endguest
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="bk-label">Email<span class="bk-req">*</span>
                        <input name="email" type="email" autocomplete="email" class="bk-input {{ $field('email') }}" value="{{ old('email', $user?->email) }}">
                        @error('email')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <x-phone-field :value="$user?->phone ?? $address?->phone" required />
                </div>
            </section>

            <section class="bk-card p-5 sm:p-6">
                <h2 class="flex items-center gap-3 text-lg font-extrabold"><span class="bk-step-no">2</span>Delivery address</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="bk-label sm:col-span-2">Contact name or business<span class="bk-req">*</span>
                        <input name="shipping_name" autocomplete="name" class="bk-input {{ $field('shipping_name') }}" value="{{ old('shipping_name', $address?->name ?? $user?->name) }}">
                        @error('shipping_name')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="bk-label sm:col-span-2">Address<span class="bk-req">*</span>
                        <input name="shipping_line1" autocomplete="address-line1" placeholder="Unit / building, street" class="bk-input {{ $field('shipping_line1') }}" value="{{ old('shipping_line1', $address?->line1) }}">
                        @error('shipping_line1')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="bk-label sm:col-span-2">Area, landmark <span class="bk-muted font-normal">(optional)</span>
                        <input name="shipping_line2" autocomplete="address-line2" class="bk-input {{ $field('shipping_line2') }}" value="{{ old('shipping_line2', $address?->line2) }}">
                        @error('shipping_line2')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="bk-label">City<span class="bk-req">*</span>
                        <input name="shipping_city" autocomplete="address-level2" class="bk-input {{ $field('shipping_city') }}" value="{{ old('shipping_city', $address?->city) }}">
                        @error('shipping_city')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="bk-label">State<span class="bk-req">*</span>
                        <input name="shipping_state" autocomplete="address-level1" class="bk-input {{ $field('shipping_state') }}" value="{{ old('shipping_state', $address?->state) }}">
                        @error('shipping_state')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="bk-label">PIN code<span class="bk-req">*</span>
                        <input name="shipping_postal_code" autocomplete="postal-code" inputmode="numeric" class="bk-input {{ $field('shipping_postal_code') }}" value="{{ old('shipping_postal_code', $address?->postal_code) }}">
                        @error('shipping_postal_code')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="bk-label">Country<span class="bk-req">*</span>
                        <input name="shipping_country" autocomplete="country-name" class="bk-input {{ $field('shipping_country') }}" value="{{ old('shipping_country', $address?->country ?? 'India') }}">
                        @error('shipping_country')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                </div>
                @auth
                    <label class="mt-4 flex items-center gap-2 text-sm">
                        <input type="checkbox" name="save_address" value="1" checked class="accent-[var(--bk-brand)]"> Save this address for next time
                    </label>
                @endauth
            </section>

            <section class="bk-card overflow-hidden">
                <h2 class="flex items-center gap-3 p-5 pb-3 text-lg font-extrabold sm:px-6"><span class="bk-step-no">3</span>Delivery</h2>
                @error('shipping_method')<p class="bk-error px-5 sm:px-6">{{ $message }}</p>@enderror
                <div class="border-t border-[var(--bk-line)]">
                    @foreach($shippingMethods as $m)
                        @php $free = $m['free_over'] !== null && $m['free_over'] > 0 && $cart['subtotal'] >= $m['free_over']; @endphp
                        <label class="bk-option" :class="shipping === @js($m['code']) && 'is-on'">
                            <input type="radio" name="shipping_method" value="{{ $m['code'] }}"
                                   :checked="shipping === @js($m['code'])" @change="setShipping(@js($m['code']))">
                            <span class="flex flex-1 justify-between gap-4">
                                <span><span class="font-bold">{{ $m['name'] }}</span>
                                    <span class="bk-muted block text-xs">{{ $m['description'] }}@if($m['eta']) · {{ $m['eta'] }}@endif</span></span>
                                <span class="font-bold bk-num">{{ ($free || $m['cost'] === 0) ? 'Free' : money($m['cost']) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="bk-card overflow-hidden">
                <h2 class="flex items-center gap-3 p-5 pb-3 text-lg font-extrabold sm:px-6"><span class="bk-step-no">4</span>Payment</h2>
                <div class="border-t border-[var(--bk-line)]">
                    @foreach($paymentMethods as $code => $name)
                        <label class="bk-option" :class="payment === @js($code) && 'is-on'">
                            <input type="radio" name="payment_method" value="{{ $code }}" x-model="payment">
                            <span class="flex flex-1 justify-between gap-4">
                                <span class="font-bold">{{ $name }}</span>
                                @if($code === 'cod')<span class="bk-muted text-sm">+ {{ money($codFee) }} fee</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('payment_method')<p class="bk-error px-5 pb-4 sm:px-6">{{ $message }}</p>@enderror
                <p class="bk-muted px-5 pb-5 text-xs sm:px-6">Payment is taken after the order is placed. No card details are stored.</p>
            </section>

            <label class="bk-label bk-card block p-5 sm:p-6">Purchase order number or delivery notes <span class="bk-muted font-normal">(optional)</span>
                <textarea name="notes" rows="2" class="bk-textarea" placeholder="e.g. PO-2291 · deliver to the back gate before 10 am">{{ old('notes') }}</textarea>
                @error('notes')<span class="bk-error">{{ $message }}</span>@enderror
            </label>
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
                <dt x-text="$store.cart.tax_label || @js($cart['tax_label'])"></dt>
                <dd x-text="$store.cart.tax_formatted || @js($cart['tax_formatted'])"></dd>
                <dt class="is-total">Total</dt><dd class="is-total" x-text="fmt(($store.cart.total ?? {{ $cart['total'] }}) + codExtra)"></dd>
            </dl>

            <button type="submit" class="bk-btn bk-btn-primary bk-btn-block mt-4 !py-3 text-base" :disabled="submitting">
                <x-heroicon-o-check-circle aria-hidden="true" /><span x-text="submitting ? 'Placing order…' : 'Place order'">Place order</span>
            </button>
            <p class="bk-muted mt-3 text-center text-xs">By placing this order you agree to our <a href="/terms" class="bk-link">terms</a> and <a href="/privacy" class="bk-link">privacy policy</a>.</p>
        </aside>
    </form>
</div>
@endsection
