@extends('layouts.app')

{{--
    The order review page: every line with picture, code, stepper and total, then a summary
    card with promo code, delivery choice and totals. Driven entirely by the shared cart store.
--}}

@section('content')
<div class="bk-wrap mt-6" x-data="{ code: '' }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Your order</h1>
            <p class="bk-muted mt-1 text-sm" x-show="$store.cart.items.length" x-cloak>
                <span x-text="$store.cart.items.length"></span> lines · <span x-text="$store.cart.count"></span> units
            </p>
        </div>
        <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-outline"><x-heroicon-o-plus aria-hidden="true" />Add more items</a>
    </div>

    <div class="bk-card mt-5 p-10 text-center" x-show="$store.cart.loaded && !$store.cart.items.length" x-cloak>
        <x-heroicon-o-shopping-cart class="mx-auto size-12 text-brand" aria-hidden="true" />
        <p class="mt-3 text-lg font-extrabold">Your order is empty</p>
        <p class="bk-muted mt-1 text-sm">Browse the catalogue, or add your whole list at once on Bulk order.</p>
        <p class="mt-5 flex flex-wrap justify-center gap-3">
            <a href="{{ route('shop.index') }}" class="bk-btn bk-btn-outline">Browse products</a>
            <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary">Bulk order</a>
        </p>
    </div>

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]" x-show="$store.cart.items.length" x-cloak>
        <div class="bk-card divide-y divide-[var(--bk-line)]">
            <template x-for="item in $store.cart.items" :key="item.key">
                <div class="flex gap-4 p-4">
                    <a :href="item.product.url" class="bk-art bk-thumb !size-20" data-tone="0">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                        <template x-if="item.product.images && item.product.images.length"><img :src="item.product.images[0]" alt=""></template>
                    </a>
                    <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto_7rem] sm:items-center">
                        <div class="min-w-0">
                            <a :href="item.product.url" class="block font-bold leading-snug hover:text-brand" x-text="item.product.name"></a>
                            <p class="bk-code mt-0.5"><span x-text="item.variant_sku || '—'"></span><span x-show="item.options_label" x-text="' · ' + item.options_label"></span></p>
                            <p class="bk-muted mt-0.5 text-xs" x-text="item.product.price_formatted + ' each'"></p>
                        </div>
                        <span class="bk-step justify-self-start">
                            <button type="button" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease"><x-heroicon-o-minus /></button>
                            <input type="number" min="0" :value="item.qty" @change="$store.cart.update(item.key, parseInt($event.target.value, 10) || 0)" aria-label="Quantity">
                            <button type="button" @click="$store.cart.update(item.key, item.qty + 1)" aria-label="Increase"><x-heroicon-o-plus /></button>
                        </span>
                        <div class="flex items-center justify-between gap-2 sm:block sm:text-right">
                            <span class="block font-extrabold bk-num" x-text="item.line_total_formatted"></span>
                            <button type="button" class="text-xs font-semibold text-[var(--bk-out)] hover:underline" @click="$store.cart.remove(item.key)">Remove</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <aside class="bk-card bk-summary p-5">
            <p class="text-lg font-extrabold">Summary</p>

            <div class="mt-4">
                <template x-if="$store.cart.coupon">
                    <p class="flex items-center justify-between gap-2 rounded-lg bg-brand-soft p-3 text-sm">
                        <span><strong x-text="$store.cart.coupon.code"></strong> <span class="bk-muted" x-text="$store.cart.coupon.label"></span></span>
                        <button type="button" class="bk-link text-xs" @click="$store.cart.removeCoupon()">Remove</button>
                    </p>
                </template>
                <template x-if="!$store.cart.coupon">
                    <form @submit.prevent="$store.cart.applyCoupon(code)" class="flex gap-2" novalidate>
                        <input x-model="code" class="bk-input !py-2" placeholder="Promo code" aria-label="Promo code">
                        <button type="submit" class="bk-btn bk-btn-outline !py-2" :disabled="$store.cart.couponBusy">Apply</button>
                    </form>
                </template>
                <p class="bk-error" x-show="$store.cart.couponError" x-text="$store.cart.couponError"></p>
            </div>

            <label class="bk-label mt-4">Delivery
                <select class="bk-select" @change="$store.cart.setShipping($event.target.value)">
                    @foreach(app(\App\Services\Cart::class)->shippingMethods() as $method)
                        <option value="{{ $method['code'] }}" :selected="$store.cart.shipping_method.code === @js($method['code'])">{{ $method['name'] }} — {{ $method['cost'] === 0 ? 'free' : money($method['cost']) }}</option>
                    @endforeach
                </select>
            </label>

            <dl class="bk-totals mt-4">
                <dt>Subtotal</dt><dd x-text="$store.cart.subtotal_formatted"></dd>
                <template x-if="$store.cart.discount > 0"><dt>Discount</dt></template>
                <template x-if="$store.cart.discount > 0"><dd x-text="'− ' + $store.cart.discount_formatted"></dd></template>
                <dt>Delivery</dt><dd x-text="$store.cart.shipping_formatted"></dd>
                <dt x-text="$store.cart.tax_label"></dt><dd x-text="$store.cart.tax_formatted"></dd>
                <dt class="is-total">Total</dt><dd class="is-total" x-text="$store.cart.total_formatted"></dd>
            </dl>

            <a href="{{ route('checkout') }}" class="bk-btn bk-btn-primary bk-btn-block mt-4 !py-3 text-base">Checkout <x-heroicon-o-chevron-right aria-hidden="true" /></a>
        </aside>
    </div>
</div>
@endsection
