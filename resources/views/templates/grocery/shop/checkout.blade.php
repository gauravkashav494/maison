@extends('layouts.app')

@section('content')
@php $old = fn (string $k, $default = null) => old($k, $default); @endphp
<x-page-head title="Checkout" :breadcrumbs="['Cart' => route('cart'), 'Checkout' => null]" />

<section class="g-container py-4" x-data="checkout({ shipping: @js($old('shipping_method', $cart['shipping_method']['code'])), payment: @js($old('payment_method', array_key_first($paymentMethods))), codFee: {{ $codFee }}, gst: @js(\App\Support\GstSupply::checkoutConfig()), cart: @js(\Illuminate\Support\Arr::only($cart, ['tax', 'tax_rate', 'tax_inclusive', 'tax_rows', 'tax_line', 'tax_formatted'])) })">

    <x-cart-unavailable-notice :message="$unavailableNotice ?? null" />
    <form method="post" action="{{ route('checkout.place') }}" class="checkout-form grid gap-4 lg:grid-cols-12 lg:gap-6" @submit="submitting = true" novalidate>
        @csrf
        <div class="space-y-3 lg:col-span-8">
            {{-- Contact & delivery address --}}
            <fieldset class="card p-4 sm:p-5">
                <legend class="sr-only">Contact and delivery address</legend>
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"><div class="flex items-center gap-3"><span class="step-num">1</span><h2 class="text-base font-extrabold">Contact &amp; delivery address</h2></div>@guest<p class="text-xs text-slate">Have an account? <a href="{{ route('login') }}?redirect=checkout" class="font-semibold text-leaf underline underline-offset-2">Log in</a> to use your saved addresses.</p>@endguest</div>
                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2.5 sm:grid-cols-12">
                    <label class="col-span-2 sm:col-span-4"><span class="label">Phone<span class="req" aria-hidden="true">*</span></span><input name="phone" type="tel" value="{{ $old('phone', $user?->phone ?? $address?->phone) }}" class="field @error('phone') field-error @enderror" autocomplete="tel" inputmode="tel">@error('phone')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="col-span-2 sm:col-span-4"><span class="label">Full name<span class="req" aria-hidden="true">*</span></span><input name="shipping_name" value="{{ $old('shipping_name', $address?->name ?? $user?->name) }}" class="field @error('shipping_name') field-error @enderror" autocomplete="name">@error('shipping_name')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="col-span-2 sm:col-span-4"><span class="label">Email (optional)</span><input name="email" type="email" value="{{ $old('email', $user?->email) }}" class="field @error('email') field-error @enderror" autocomplete="email">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="col-span-2 sm:col-span-8"><span class="label">House / flat, building, street<span class="req" aria-hidden="true">*</span></span><input name="shipping_line1" value="{{ $old('shipping_line1', $address?->line1) }}" class="field @error('shipping_line1') field-error @enderror" autocomplete="address-line1">@error('shipping_line1')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="col-span-2 sm:col-span-4"><span class="label">Landmark, area (optional)</span><input name="shipping_line2" value="{{ $old('shipping_line2', $address?->line2) }}" class="field" autocomplete="address-line2">@error('shipping_line2')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-3"><span class="label">City<span class="req" aria-hidden="true">*</span></span><input name="shipping_city" value="{{ $old('shipping_city', $address?->city) }}" class="field @error('shipping_city') field-error @enderror" autocomplete="address-level2">@error('shipping_city')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-3"><span class="label">State<span class="req" aria-hidden="true">*</span></span><input name="shipping_state" value="{{ $old('shipping_state', $address?->state) }}" class="field @error('shipping_state') field-error @enderror" autocomplete="address-level1">@error('shipping_state')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-3"><span class="label">Pincode<span class="req" aria-hidden="true">*</span></span><input name="shipping_postal_code" value="{{ $old('shipping_postal_code', $address?->postal_code) }}" class="field @error('shipping_postal_code') field-error @enderror" autocomplete="postal-code" inputmode="numeric">@error('shipping_postal_code')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-3"><span class="label">Country<span class="req" aria-hidden="true">*</span></span><input name="shipping_country" value="{{ $old('shipping_country', $address?->country ?? 'India') }}" class="field" autocomplete="country-name">@error('shipping_country')<span class="error-text">{{ $message }}</span>@enderror</label>
                </div>
                @auth<label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="save_address" value="1" class="check" checked> Save this address for next time</label>@endauth
            </fieldset>

            {{-- Delivery & payment --}}
            <div class="card divide-y divide-line">
            <fieldset class="p-4 sm:p-5">
                <legend class="sr-only">Delivery option</legend>
                <div class="flex items-center gap-3"><span class="step-num">2</span><h2 class="text-base font-extrabold">Delivery option<span class="req" aria-hidden="true">*</span></h2></div>
                @error('shipping_method')<p class="error-text">{{ $message }}</p>@enderror
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach($shippingMethods as $m)
                        @php $free = $m['free_over'] !== null && $m['free_over'] > 0 && $cart['subtotal'] >= $m['free_over']; @endphp
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-2.5 transition-colors" :class="shipping === @js($m['code']) ? 'border-leaf bg-leaf-light/50' : 'border-line hover:border-line-strong'">
                            <input type="radio" name="shipping_method" value="{{ $m['code'] }}" class="check mt-1" :checked="shipping === @js($m['code'])" @change="setShipping(@js($m['code']))">
                            <span class="flex-1">
                                <span class="flex items-baseline justify-between gap-3"><span class="text-sm font-bold">{{ $m['name'] }}</span><span class="text-sm font-bold tabular {{ ($free || $m['cost'] <= 0) ? 'text-leaf' : '' }}">{{ ($free || $m['cost'] <= 0) ? 'FREE' : money($m['cost']) }}</span></span>
                                <span class="mt-0.5 block text-xs text-slate">{{ $m['description'] }}@if($m['eta']) · {{ $m['eta'] }}@endif</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Payment --}}
            <fieldset class="p-4 sm:p-5">
                <legend class="sr-only">Payment</legend>
                <div class="flex items-center gap-3"><span class="step-num">3</span><h2 class="text-base font-extrabold">Payment method<span class="req" aria-hidden="true">*</span></h2></div>
                @if(array_diff(array_keys($paymentMethods), ['cod']))<p class="mt-2 flex items-center gap-1.5 text-xs text-slate"><x-ico name="lock" :size="12" /> 100% secure. We never store card details.</p>@endif
                <div class="mt-3 grid gap-2">
                    @foreach($paymentMethods as $code => $name)
                        <div class="rounded-xl border transition-colors" :class="payment === @js($code) ? 'border-leaf' : 'border-line'">
                            <label class="flex cursor-pointer items-center gap-3 px-3 py-2.5">
                                <input type="radio" name="payment_method" value="{{ $code }}" class="check" x-model="payment">
                                <span class="flex-1 text-sm font-bold">{{ $name }}@if($code === 'cod')<span class="block text-xs font-normal text-slate">Pay by cash or UPI at your door</span>@endif</span>
                                <span class="text-[0.6875rem] font-semibold text-mist">
                                    @switch($code)
                                        @case('card') Visa · Mastercard · RuPay @break
                                        @case('upi') GPay · PhonePe · Paytm @break
                                        @case('wallet') Paytm · Amazon Pay @break
                                        @case('cod') @if($codFee > 0)+ {{ money($codFee) }} fee @else No extra fee @endif @break
                                    @endswitch
                                </span>
                            </label>
                            @if($code !== 'cod')
                            <div x-show="payment === @js($code)" x-collapse x-cloak class="border-t border-line bg-paper px-4 py-4 text-sm">
                                @switch($code)
                                    @case('card')
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <label class="sm:col-span-2"><span class="label">Card number</span><input inputmode="numeric" placeholder="•••• •••• •••• ••••" class="field" autocomplete="cc-number"></label>
                                            <label><span class="label">Expiry</span><input placeholder="MM / YY" class="field" autocomplete="cc-exp"></label>
                                            <label><span class="label">CVV</span><input inputmode="numeric" placeholder="•••" class="field" autocomplete="cc-csc"></label>
                                        </div>
                                        <p class="mt-2 text-xs text-mist">Card details are captured by the payment gateway on the next step.</p>
                                        @break
                                    @case('upi')
                                        <label><span class="label">UPI ID</span><input placeholder="name@upi" class="field"></label>
                                        <p class="mt-2 text-xs text-mist">You’ll get a collect request in your UPI app to approve.</p>
                                        @break
                                    @case('wallet') <p class="text-slate">You’ll be redirected to your wallet to authorise the payment.</p> @break
                                    @case('netbanking')
                                        <label><span class="label">Bank</span><select class="field"><option>HDFC Bank</option><option>ICICI Bank</option><option>State Bank of India</option><option>Axis Bank</option><option>Kotak Mahindra</option></select></label>
                                        @break
                                    @case('cod') <p class="text-slate">Pay in cash or by UPI to the delivery partner at your door.@if($codFee > 0) A handling fee of {{ money($codFee) }} applies.@endif</p> @break
                                @endswitch
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('payment_method')<p class="error-text">{{ $message }}</p>@enderror
            </fieldset>
            </div>

            <div x-data="{ open: @js(filled(old('notes')) || $errors->has('notes')) }">
                <button type="button" class="text-sm font-semibold text-leaf" @click="open = !open" :aria-expanded="open"><span x-text="open ? '−' : '+'">+</span> Add delivery instructions</button>
                <label x-show="open" x-collapse x-cloak class="mt-2 block"><span class="sr-only">Delivery instructions</span><textarea name="notes" rows="2" class="field" placeholder="e.g. Leave at the door, call on arrival, avoid ringing the bell…">{{ $old('notes') }}</textarea>@error('notes')<span class="error-text">{{ $message }}</span>@enderror</label>
            </div>
        </div>

        {{-- Summary --}}
        <aside class="lg:col-span-4">
            <div class="card p-4 sm:p-5 lg:sticky lg:top-[7.5rem]">
                <p class="text-sm font-extrabold">Order summary <span class="font-semibold text-slate">({{ $cart['count'] }} {{ Str::plural('item', $cart['count']) }})</span></p>
                <ul class="mt-3 max-h-64 divide-y divide-line overflow-y-auto">
                    @foreach($cart['items'] as $item)
                        <li class="flex items-center gap-3 py-2.5">
                            <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-line bg-paper"><img src="{{ $item['product']['images'][0] ?? '' }}" alt="" class="img-cover"><span class="absolute -right-0 -top-0 grid h-4 min-w-4 place-items-center rounded-bl-md bg-ink px-1 text-[0.625rem] font-bold text-white">{{ $item['qty'] }}</span></div>
                            <div class="min-w-0 flex-1"><p class="truncate text-xs font-bold">{{ $item['product']['name'] }}</p><p class="text-[0.6875rem] text-slate">{{ $item['size'] }}</p></div>
                            <span class="text-xs font-bold tabular">{{ $item['line_total_formatted'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-3 space-y-2 border-t border-line pt-3 text-sm" x-data>
                    <div class="flex justify-between"><dt class="text-slate">Item total</dt><dd class="tabular" x-text="$store.cart.subtotal_formatted || @js($cart['subtotal_formatted'])"></dd></div>
                    <div class="flex justify-between" x-show="$store.cart.discount > 0"><dt class="text-slate">Coupon <span x-text="$store.cart.coupon ? '(' + $store.cart.coupon.code + ')' : ''"></span></dt><dd class="tabular text-leaf" x-text="'− ' + $store.cart.discount_formatted"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate">Delivery</dt><dd class="tabular" x-text="($store.cart.loaded ? $store.cart.shipping : @js($cart['shipping'])) === 0 ? 'FREE' : ($store.cart.shipping_formatted || @js($cart['shipping_formatted']))"></dd></div>
                    <div class="flex justify-between" x-show="codExtra > 0"><dt class="text-slate">COD handling fee</dt><dd class="tabular" x-text="fmt(codExtra)"></dd></div>
                    @if(shows_tax_row())
                        {{-- GST, or IGST / CGST + SGST once the state is typed (as the order will be). --}}<template x-for="row in taxRows" :key="row.label"><div class="flex justify-between"><dt class="text-slate" x-text="row.label"></dt><dd class="tabular" x-text="row.amount"></dd></div></template>
                    @endif
                    <div class="flex justify-between border-t border-line pt-3 text-base font-extrabold"><dt>To pay</dt><dd class="tabular" x-text="fmt(($store.cart.loaded ? $store.cart.total : {{ $cart['total'] }}) + codExtra)"></dd></div>
                </dl>
                @if(prices_include_tax())<p class="mt-1 text-right text-xs text-slate">Inclusive of all taxes</p>@endif
                <button type="submit" :disabled="submitting || $store.cart.unavailable?.length > 0" class="btn btn-primary btn-lg btn-block mt-4"><x-ico name="lock" :size="14" /> <span x-text="submitting ? 'Placing order…' : 'Place order'"></span></button>
                @guest<p class="mt-2 text-center text-xs text-slate">We will send a code on WhatsApp to confirm your number and create your account.</p>@endguest
                <p class="mt-3 text-center text-[0.6875rem] leading-relaxed text-mist">By placing your order you agree to our <a href="/terms" class="underline">Terms</a> and <a href="/privacy" class="underline">Privacy Policy</a>.</p>
            </div>
        </aside>
    </form>
</section>
@endsection
