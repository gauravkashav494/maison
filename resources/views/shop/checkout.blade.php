@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
@php
    $field = 'input-luxe';
    $label = 'block text-[0.6875rem] uppercase tracking-[0.2em] text-smoke';
    $old = fn (string $k, $default = null) => old($k, $default);
@endphp
<x-page-hero eyebrow="Secure checkout" title="Checkout" :breadcrumbs="['Shopping Bag' => route('cart'), 'Checkout' => null]" />

<section class="container-luxe py-12 lg:py-16" x-data="checkout({ shipping: @js($old('shipping_method', $cart['shipping_method']['code'])), payment: @js($old('payment_method', array_key_first($paymentMethods))), codFee: {{ $codFee }} })">
    @if($errors->any())
        <div class="mb-8 border border-rouge/30 bg-rouge/5 px-5 py-4 text-sm text-rouge">Please check the highlighted fields below.</div>
    @endif

    <form method="post" action="{{ route('checkout.place') }}" class="grid gap-12 lg:grid-cols-12 lg:gap-16" @submit="submitting = true">
        @csrf
        <div class="space-y-12 lg:col-span-7">
            {{-- Contact --}}
            <fieldset>
                <legend class="flex items-baseline gap-3"><span class="font-serif text-3xl">01</span><span class="font-serif text-2xl">Contact information</span></legend>
                @guest<p class="mt-2 text-sm text-smoke">Have an account? <a href="{{ route('login') }}?redirect=checkout" class="underline underline-offset-4">Sign in</a> for a faster checkout.</p>@endguest
                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    <label><span class="{{ $label }}">Email</span><input name="email" type="email" required value="{{ $old('email', $user?->email) }}" class="{{ $field }}" autocomplete="email">@error('email')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label><span class="{{ $label }}">Phone</span><input name="phone" type="tel" required value="{{ $old('phone', $user?->phone ?? $address?->phone) }}" class="{{ $field }}" autocomplete="tel">@error('phone')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                </div>
            </fieldset>

            {{-- Shipping address --}}
            <fieldset>
                <legend class="flex items-baseline gap-3"><span class="font-serif text-3xl">02</span><span class="font-serif text-2xl">Shipping address</span></legend>
                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    <label class="sm:col-span-2"><span class="{{ $label }}">Full name</span><input name="shipping_name" required value="{{ $old('shipping_name', $address?->name ?? $user?->name) }}" class="{{ $field }}" autocomplete="name">@error('shipping_name')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-2"><span class="{{ $label }}">Address</span><input name="shipping_line1" required value="{{ $old('shipping_line1', $address?->line1) }}" class="{{ $field }}" autocomplete="address-line1" placeholder="House / flat, street">@error('shipping_line1')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-2"><span class="{{ $label }}">Apartment, landmark (optional)</span><input name="shipping_line2" value="{{ $old('shipping_line2', $address?->line2) }}" class="{{ $field }}" autocomplete="address-line2"></label>
                    <label><span class="{{ $label }}">City</span><input name="shipping_city" required value="{{ $old('shipping_city', $address?->city) }}" class="{{ $field }}" autocomplete="address-level2">@error('shipping_city')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label><span class="{{ $label }}">State</span><input name="shipping_state" required value="{{ $old('shipping_state', $address?->state) }}" class="{{ $field }}" autocomplete="address-level1">@error('shipping_state')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label><span class="{{ $label }}">Postal code</span><input name="shipping_postal_code" required value="{{ $old('shipping_postal_code', $address?->postal_code) }}" class="{{ $field }}" autocomplete="postal-code" inputmode="numeric">@error('shipping_postal_code')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label><span class="{{ $label }}">Country</span><input name="shipping_country" required value="{{ $old('shipping_country', $address?->country ?? 'India') }}" class="{{ $field }}" autocomplete="country-name"></label>
                </div>
                @auth<label class="mt-5 flex items-center gap-3 text-sm"><input type="checkbox" name="save_address" value="1" class="accent-ink" checked> Save this address to my account</label>@endauth
            </fieldset>

            {{-- Shipping method --}}
            <fieldset>
                <legend class="flex items-baseline gap-3"><span class="font-serif text-3xl">03</span><span class="font-serif text-2xl">Delivery method</span></legend>
                <div class="mt-6 divide-y divide-ink/10 border border-ink/15">
                    @foreach($shippingMethods as $m)
                        @php $free = $m['free_over'] !== null && $m['free_over'] > 0 && $cart['subtotal'] >= $m['free_over']; @endphp
                        <label class="flex cursor-pointer items-start gap-4 px-5 py-4 transition-colors" :class="shipping === @js($m['code']) && 'bg-cream'">
                            <input type="radio" name="shipping_method" value="{{ $m['code'] }}" class="mt-1 accent-ink" :checked="shipping === @js($m['code'])" @change="setShipping(@js($m['code']))">
                            <span class="flex-1">
                                <span class="flex items-baseline justify-between gap-4"><span class="text-sm font-medium">{{ $m['name'] }}</span><span class="text-sm tabular-nums">{{ ($free || $m['cost'] === 0) ? 'Complimentary' : money($m['cost']) }}</span></span>
                                <span class="mt-0.5 block text-xs text-smoke">{{ $m['description'] }}@if($m['eta']) · {{ $m['eta'] }}@endif</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Payment --}}
            <fieldset>
                <legend class="flex items-baseline gap-3"><span class="font-serif text-3xl">04</span><span class="font-serif text-2xl">Payment</span></legend>
                <p class="mt-2 flex items-center gap-2 text-xs text-smoke"><x-ico name="lock" :size="12" /> All transactions are encrypted. We never store card details.</p>
                <div class="mt-6 divide-y divide-ink/10 border border-ink/15">
                    @foreach($paymentMethods as $code => $name)
                        <div>
                            <label class="flex cursor-pointer items-center gap-4 px-5 py-4" :class="payment === @js($code) && 'bg-cream'">
                                <input type="radio" name="payment_method" value="{{ $code }}" class="accent-ink" x-model="payment">
                                <span class="flex-1 text-sm font-medium">{{ $name }}</span>
                                <span class="text-[0.5625rem] uppercase tracking-wider text-taupe">
                                    @switch($code)
                                        @case('card') Visa · MC · Amex · RuPay @break
                                        @case('upi') GPay · PhonePe · Paytm @break
                                        @case('wallet') Paytm · Amazon Pay @break
                                        @case('cod') + {{ money($codFee) }} fee @break
                                    @endswitch
                                </span>
                            </label>
                            <div x-show="payment === @js($code)" x-collapse x-cloak class="border-t border-ink/10 bg-cream px-5 py-5">
                                @switch($code)
                                    @case('card')
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <label class="sm:col-span-2"><span class="{{ $label }}">Card number</span><input inputmode="numeric" placeholder="•••• •••• •••• ••••" class="{{ $field }}" autocomplete="cc-number"></label>
                                            <label><span class="{{ $label }}">Expiry</span><input placeholder="MM / YY" class="{{ $field }}" autocomplete="cc-exp"></label>
                                            <label><span class="{{ $label }}">CVC</span><input inputmode="numeric" placeholder="•••" class="{{ $field }}" autocomplete="cc-csc"></label>
                                        </div>
                                        <p class="mt-3 text-[0.6875rem] text-taupe">Card capture is handled by the payment gateway on the next step; details are shown here for design only.</p>
                                        @break
                                    @case('upi')
                                        <label><span class="{{ $label }}">UPI ID</span><input placeholder="name@bank" class="{{ $field }}"></label>
                                        <p class="mt-3 text-[0.6875rem] text-taupe">You will receive a collect request in your UPI app to approve.</p>
                                        @break
                                    @case('wallet')
                                        <p class="text-sm text-smoke">You will be redirected to your wallet to authorise the payment.</p>
                                        @break
                                    @case('netbanking')
                                        <label><span class="{{ $label }}">Bank</span><select class="{{ $field }}"><option>HDFC Bank</option><option>ICICI Bank</option><option>State Bank of India</option><option>Axis Bank</option><option>Kotak Mahindra</option></select></label>
                                        @break
                                    @case('cod')
                                        <p class="text-sm text-smoke">Pay in cash or by card to the courier on delivery. A handling fee of {{ money($codFee) }} applies.</p>
                                        @break
                                @endswitch
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('payment_method')<p class="mt-2 text-xs text-rouge">{{ $message }}</p>@enderror
            </fieldset>

            <label class="block"><span class="{{ $label }}">Order notes (optional)</span><textarea name="notes" rows="2" class="{{ $field }}" placeholder="Gift message, delivery instructions…">{{ $old('notes') }}</textarea></label>
        </div>

        {{-- Summary --}}
        <aside class="lg:col-span-5">
            <div class="border border-ink/10 bg-cream p-6 lg:sticky lg:top-28 lg:p-8">
                <h2 class="font-serif text-2xl">Your order</h2>
                <ul class="mt-6 divide-y divide-ink/10">
                    @foreach($cart['items'] as $item)
                        <li class="flex items-center gap-4 py-4">
                            <div class="relative h-20 w-16 shrink-0 overflow-hidden bg-sand">
                                <img src="{{ $item['product']['images'][0] ?? '' }}" alt="" class="img-cover">
                                <span class="absolute -right-0 -top-0 grid h-5 min-w-5 place-items-center bg-ink px-1 text-[0.625rem] text-ivory">{{ $item['qty'] }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-serif text-[0.9375rem]">{{ $item['product']['name'] }}</p>
                                <p class="text-xs text-smoke">{{ $item['color'] ? $item['color'].' · ' : '' }}{{ $item['size'] }}</p>
                            </div>
                            <span class="text-sm tabular-nums">{{ $item['line_total_formatted'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-3 border-t border-ink/10 pt-5 text-sm" x-data>
                    <div class="flex justify-between"><dt class="text-smoke">Subtotal</dt><dd class="tabular-nums" x-text="$store.cart.subtotal_formatted || @js($cart['subtotal_formatted'])"></dd></div>
                    <div class="flex justify-between" x-show="$store.cart.discount > 0"><dt class="text-smoke">Discount <span class="text-[0.625rem]" x-text="$store.cart.coupon ? '(' + $store.cart.coupon.code + ')' : ''"></span></dt><dd class="tabular-nums text-emerald-700" x-text="'− ' + $store.cart.discount_formatted"></dd></div>
                    <div class="flex justify-between"><dt class="text-smoke">Shipping</dt><dd class="tabular-nums" x-text="$store.cart.shipping_formatted || @js($cart['shipping_formatted'])"></dd></div>
                    <div class="flex justify-between" x-show="codExtra > 0"><dt class="text-smoke">Cash on delivery fee</dt><dd class="tabular-nums" x-text="fmt(codExtra)"></dd></div>
                    <div class="flex justify-between"><dt class="text-smoke" x-text="'Taxes (' + ($store.cart.tax_label || @js($cart['tax_label'])) + ')'"></dt><dd class="tabular-nums" x-text="$store.cart.tax_formatted || @js($cart['tax_formatted'])"></dd></div>
                    <div class="flex justify-between border-t border-ink/10 pt-3 text-lg"><dt>Total</dt><dd class="tabular-nums" x-text="fmt(($store.cart.total ?? {{ $cart['total'] }}) + codExtra)"></dd></div>
                </dl>
                <button type="submit" :disabled="submitting" class="btn btn-primary btn-lg mt-6 w-full"><x-ico name="lock" :size="12" :stroke="1.5" /> <span x-text="submitting ? 'Placing order…' : 'Place order'"></span></button>
                <p class="mt-4 text-center text-[0.6875rem] leading-relaxed text-taupe">By placing your order you agree to our <a href="/terms" class="underline">Terms</a> and <a href="/privacy" class="underline">Privacy Policy</a>. 30-day returns on every piece.</p>
                <ul class="mt-5 grid grid-cols-3 gap-2 border-t border-ink/10 pt-5 text-center text-[0.5625rem] uppercase tracking-[0.15em] text-smoke">
                    <li><x-ico name="lock" :size="16" :stroke="1" class="mx-auto mb-1" />Secure</li>
                    <li><x-ico name="truck" :size="16" :stroke="1" class="mx-auto mb-1" />Tracked</li>
                    <li><x-ico name="rotate" :size="16" :stroke="1" class="mx-auto mb-1" />Free returns</li>
                </ul>
            </div>
        </aside>
    </form>
</section>
@endsection
