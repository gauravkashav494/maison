@extends('layouts.app')

@section('content')
@php
    $field = 'input-luxe';
    $label = 'block text-[0.625rem] uppercase tracking-[0.18em] text-smoke';
    $old = fn (string $k, $default = null) => old($k, $default);
@endphp
<section class="container-luxe pb-12 pt-20 lg:pb-16 lg:pt-36" x-data="checkout({ shipping: @js($old('shipping_method', $cart['shipping_method']['code'])), payment: @js($old('payment_method', array_key_first($paymentMethods))), codFee: {{ $codFee }}, gst: @js(\App\Support\GstSupply::checkoutConfig()), cart: @js(\Illuminate\Support\Arr::only($cart, ['tax', 'tax_rate', 'tax_inclusive', 'tax_rows', 'tax_line', 'tax_formatted'])) })">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3 border-b border-ink/10 pb-4">
        <h1 class="font-serif text-3xl lg:text-4xl">Checkout</h1>
        <a href="{{ route('cart') }}" class="text-xs uppercase tracking-[0.2em] text-smoke underline-offset-4 hover:underline">← Back to cart</a>
    </div>

    <x-cart-unavailable-notice :message="$unavailableNotice ?? null" />
    <form method="post" action="{{ route('checkout.place') }}" class="checkout-form grid gap-8 lg:grid-cols-12 lg:gap-12" @submit="submitting = true" novalidate>
        @csrf
        <div class="space-y-6 lg:col-span-7">
            {{-- Contact & shipping address --}}
            <fieldset>
                <legend class="flex w-full flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <span class="flex items-baseline gap-2"><span class="font-serif text-lg text-taupe">01</span><span class="font-serif text-xl">Contact &amp; shipping</span></span>
                    @guest<span class="text-xs text-smoke">Have an account? <a href="{{ route('login') }}?redirect=checkout" class="underline underline-offset-4">Sign in</a></span>@endguest
                </legend>
                @if($salesOrder ?? false)
                    @include('partials.checkout.sales-order', ['errorClass' => 'text-xs text-rouge', 'radioClass' => 'accent-ink'])
                @endif
                <div class="mt-3 grid grid-cols-2 gap-x-5 gap-y-3 sm:grid-cols-12">
                    <label class="col-span-2 sm:col-span-4"><span class="{{ $label }}">Phone<span class="req" aria-hidden="true">*</span></span><input name="phone" type="tel" value="{{ $old('phone', $user?->phone ?? $address?->phone) }}" class="{{ $field }}" autocomplete="tel">@error('phone')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="col-span-2 sm:col-span-4"><span class="{{ $label }}">Full name<span class="req" aria-hidden="true">*</span></span><input name="shipping_name" value="{{ $old('shipping_name', $address?->name ?? $user?->name) }}" class="{{ $field }}" autocomplete="name">@error('shipping_name')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="col-span-2 sm:col-span-4"><span class="{{ $label }}">Email (optional)</span><input name="email" type="email" value="{{ $old('email', $user?->email) }}" class="{{ $field }}" autocomplete="email">@error('email')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="col-span-2 sm:col-span-8"><span class="{{ $label }}">Address<span class="req" aria-hidden="true">*</span></span><input name="shipping_line1" value="{{ $old('shipping_line1', $address?->line1) }}" class="{{ $field }}" autocomplete="address-line1" placeholder="House / flat, street">@error('shipping_line1')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="col-span-2 sm:col-span-4"><span class="{{ $label }}">Landmark (optional)</span><input name="shipping_line2" value="{{ $old('shipping_line2', $address?->line2) }}" class="{{ $field }}" autocomplete="address-line2">@error('shipping_line2')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-3"><span class="{{ $label }}">City<span class="req" aria-hidden="true">*</span></span><input name="shipping_city" value="{{ $old('shipping_city', $address?->city) }}" class="{{ $field }}" autocomplete="address-level2">@error('shipping_city')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-3"><span class="{{ $label }}">State<span class="req" aria-hidden="true">*</span></span><input name="shipping_state" value="{{ $old('shipping_state', $address?->state) }}" class="{{ $field }}" autocomplete="address-level1">@error('shipping_state')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-3"><span class="{{ $label }}">Postal code<span class="req" aria-hidden="true">*</span></span><input name="shipping_postal_code" value="{{ $old('shipping_postal_code', $address?->postal_code) }}" class="{{ $field }}" autocomplete="postal-code" inputmode="numeric">@error('shipping_postal_code')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-3"><span class="{{ $label }}">Country<span class="req" aria-hidden="true">*</span></span><input name="shipping_country" value="India" readonly aria-readonly="true" title="We deliver within India only" class="{{ $field }}" autocomplete="country-name">@error('shipping_country')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                </div>
                @auth<label class="mt-4 flex items-center gap-3 text-sm"><input type="checkbox" name="save_address" value="1" class="accent-ink" checked> Save this address to my account</label>@endauth
            </fieldset>

            {{-- Shipping method --}}
            <fieldset>
                <legend class="flex items-baseline gap-2"><span class="font-serif text-lg text-taupe">02</span><span class="font-serif text-xl">Delivery method<span class="req" aria-hidden="true">*</span></span></legend>
                @error('shipping_method')<p class="mt-2 text-xs text-rouge">{{ $message }}</p>@enderror
                <div class="mt-3 grid gap-px border border-ink/15 bg-ink/10 sm:grid-cols-2">
                    @foreach($shippingMethods as $m)
                        @php $free = $m['free_over'] !== null && $m['free_over'] > 0 && $cart['subtotal'] >= $m['free_over']; @endphp
                        <label class="flex cursor-pointer items-start gap-3 bg-ivory px-4 py-3 transition-colors" :class="shipping === @js($m['code']) && '!bg-cream'">
                            <input type="radio" name="shipping_method" value="{{ $m['code'] }}" class="mt-1 accent-ink" :checked="shipping === @js($m['code'])" @change="setShipping(@js($m['code']))">
                            <span class="flex-1">
                                <span class="flex items-baseline justify-between gap-4"><span class="text-sm font-medium">{{ $m['name'] }}</span><span class="text-sm tabular-nums">{{ ($free || $m['cost'] <= 0) ? 'Complimentary' : money($m['cost']) }}</span></span>
                                <span class="mt-0.5 block text-xs text-smoke">{{ $m['description'] }}@if($m['eta']) · {{ $m['eta'] }}@endif</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Payment --}}
            <fieldset>
                <legend class="flex items-baseline gap-2"><span class="font-serif text-lg text-taupe">03</span><span class="font-serif text-xl">Payment<span class="req" aria-hidden="true">*</span></span></legend>
                @if(array_diff(array_keys($paymentMethods), ['cod']))<p class="mt-2 flex items-center gap-2 text-xs text-smoke"><x-ico name="lock" :size="12" /> All transactions are encrypted. We never store card details.</p>@endif
                <div class="mt-3 divide-y divide-ink/10 border border-ink/15">
                    @foreach($paymentMethods as $code => $name)
                        <div>
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-3" :class="payment === @js($code) && 'bg-cream'">
                                <input type="radio" name="payment_method" value="{{ $code }}" class="accent-ink" x-model="payment">
                                <span class="flex-1 text-sm font-medium">{{ $name }}@if($code === 'cod')<span class="block text-xs font-normal text-smoke">Pay by cash or card to the courier</span>@endif</span>
                                <span class="text-[0.5625rem] uppercase tracking-wider text-taupe">
                                    @switch($code)
                                        @case('card') Visa · MC · Amex · RuPay @break
                                        @case('upi') GPay · PhonePe · Paytm @break
                                        @case('wallet') Paytm · Amazon Pay @break
                                        @case('cod') + {{ money($codFee) }} fee @break
                                    @endswitch
                                </span>
                            </label>
                            @if($code !== 'cod')
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
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('payment_method')<p class="mt-2 text-xs text-rouge">{{ $message }}</p>@enderror
            </fieldset>

            <div x-data="{ open: @js(filled(old('notes')) || $errors->has('notes')) }">
                <button type="button" class="text-xs uppercase tracking-[0.2em] text-smoke underline-offset-4 hover:underline" @click="open = !open" :aria-expanded="open"><span x-text="open ? '−' : '+'">+</span> Add order notes</button>
                <label x-show="open" x-collapse x-cloak class="mt-2 block"><span class="sr-only">Order notes</span><textarea name="notes" rows="2" class="{{ $field }}" placeholder="Gift message, delivery instructions…">{{ $old('notes') }}</textarea>@error('notes')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
            </div>
        </div>

        {{-- Summary --}}
        <aside class="lg:col-span-5">
            <div class="border border-ink/10 bg-cream p-5 lg:sticky lg:top-28 lg:p-6">
                <h2 class="font-serif text-xl">Your order</h2>
                <ul class="mt-3 max-h-72 divide-y divide-ink/10 overflow-y-auto">
                    @foreach($cart['items'] as $item)
                        <li class="flex items-center gap-3 py-3">
                            <div class="relative h-14 w-11 shrink-0 overflow-hidden bg-sand">
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
                <dl class="mt-3 space-y-2 border-t border-ink/10 pt-4 text-sm" x-data>
                    <div class="flex justify-between"><dt class="text-smoke">Subtotal</dt><dd class="tabular-nums" x-text="$store.cart.subtotal_formatted || @js($cart['subtotal_formatted'])"></dd></div>
                    <div class="flex justify-between" x-show="$store.cart.discount > 0"><dt class="text-smoke">Discount <span class="text-[0.625rem]" x-text="$store.cart.coupon ? '(' + $store.cart.coupon.code + ')' : ''"></span></dt><dd class="tabular-nums text-emerald-700" x-text="'− ' + $store.cart.discount_formatted"></dd></div>
                    <div class="flex justify-between"><dt class="text-smoke">Shipping</dt><dd class="tabular-nums" x-text="$store.cart.shipping_formatted || @js($cart['shipping_formatted'])"></dd></div>
                    <div class="flex justify-between" x-show="codExtra > 0"><dt class="text-smoke">Cash on delivery fee</dt><dd class="tabular-nums" x-text="fmt(codExtra)"></dd></div>
                    @if(shows_tax_row())
                        {{-- GST, or IGST / CGST + SGST once the state is typed (as the order will be). --}}<template x-for="row in taxRows" :key="row.label"><div class="flex justify-between"><dt class="text-smoke" x-text="row.label"></dt><dd class="tabular-nums" x-text="row.amount"></dd></div></template>
                    @endif
                    <div class="flex justify-between border-t border-ink/10 pt-3 text-lg"><dt>Total</dt><dd class="tabular-nums" x-text="fmt(($store.cart.total ?? {{ $cart['total'] }}) + codExtra)"></dd></div>
                </dl>
                @include('partials.checkout.promo-code', ['containerClass' => 'mt-4 border-t border-ink/10 pt-4', 'labelClass' => 'eyebrow text-taupe', 'inputClass' => 'input-luxe min-w-0 flex-1 uppercase', 'buttonClass' => 'shrink-0 border-b border-ink pb-2 text-[0.6875rem] uppercase tracking-[0.16em] hover:opacity-60 disabled:opacity-50', 'couponClass' => 'mt-2 flex items-center justify-between gap-2 bg-cream px-3 py-2 text-sm', 'removeClass' => 'shrink-0 text-[0.625rem] uppercase tracking-widest text-smoke hover:text-ink', 'errorClass' => 'mt-2 text-sm text-rouge'])
                @if(prices_include_tax())<p class="mt-1 text-right text-xs text-smoke">Inclusive of all taxes</p>@endif
                <button type="submit" :disabled="submitting || $store.cart.unavailable?.length > 0" class="btn btn-primary btn-lg mt-5 w-full"><x-ico name="lock" :size="12" :stroke="1.5" /> <span x-text="submitting ? 'Placing order…' : 'Place order'"></span></button>
                @guest @otp<p class="mt-3 text-center text-[0.6875rem] leading-relaxed text-taupe">We will send a code on WhatsApp to confirm your number and create your account.</p>@endotp @endguest
                <p class="mt-4 text-center text-[0.6875rem] leading-relaxed text-taupe">By placing your order you agree to our <a href="/terms" class="underline">Terms</a> and <a href="/privacy" class="underline">Privacy Policy</a>. 30-day returns on every piece.</p>
            </div>
        </aside>
    </form>
</section>
@endsection
