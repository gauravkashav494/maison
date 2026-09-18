@extends('layouts.app')

@section('content')
<x-page-head title="My cart" :breadcrumbs="['Cart' => null]" />

<section class="g-container py-6" x-data="{ code: '' }">
    <div x-show="$store.cart.loaded && !$store.cart.items.length" x-cloak>
        <x-empty-state icon="cart" title="Your cart is empty" text="Add fresh produce, daily essentials and snacks — delivery is just minutes away.">
            <a href="{{ route('shop.index') }}" class="btn btn-primary btn-lg">Start shopping</a>
        </x-empty-state>
    </div>

    <div x-show="$store.cart.items.length" x-cloak class="grid gap-6 lg:grid-cols-12 lg:gap-8">
        <div class="space-y-4 lg:col-span-8">
            {{-- Free delivery progress --}}
            <div class="card p-4" x-show="$store.cart.free_shipping_threshold > 0">
                <p class="text-sm" x-text="$store.cart.remaining > 0 ? 'Add ' + $store.cart.remaining_formatted + ' more to unlock free delivery' : 'You’ve unlocked free delivery 🎉'"></p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full bg-leaf transition-all duration-500" :style="`width:${Math.min(100, ($store.cart.progress || 0) * 100)}%`"></div></div>
            </div>

            <div class="card divide-y divide-line">
                <div class="flex items-center justify-between px-4 py-3">
                    <p class="text-sm font-extrabold">Items <span class="font-semibold text-slate" x-text="'(' + $store.cart.count + ')'"></span></p>
                    <p class="text-xs font-semibold text-leaf" x-show="$store.cart.savings > 0"><x-ico name="sparkle" :size="12" class="inline" /> Saving <span x-text="$store.cart.savings_formatted"></span></p>
                </div>
                <template x-for="item in $store.cart.items" :key="item.key">
                    <div class="flex gap-4 px-4 py-4">
                        <a :href="item.product.url" class="h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-line bg-paper sm:h-24 sm:w-24"><img :src="item.product.images[0]" :alt="item.product.name" class="img-cover"></a>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate" x-text="item.product.brand || item.product.category"></p>
                                    <a :href="item.product.url" class="line-clamp-2 text-sm font-bold leading-snug hover:text-leaf" x-text="item.product.name"></a>
                                    <p class="mt-0.5 text-xs text-slate" x-text="item.size"></p>
                                </div>
                                <button type="button" @click="$store.cart.remove(item.key)" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-mist hover:bg-paper hover:text-berry" aria-label="Remove"><x-ico name="trash" :size="16" /></button>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <div class="flex items-baseline gap-2"><span class="text-base font-extrabold tabular" x-text="item.line_total_formatted"></span><span x-show="item.product.compare_at_price" class="text-xs text-mist strike" x-text="'₹' + (item.product.compare_at_price * item.qty).toLocaleString('en-IN')"></span><span class="text-xs text-slate" x-text="item.qty > 1 ? item.product.price_formatted + ' each' : ''"></span></div>
                                <div class="stepper">
                                    <button type="button" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease"><x-ico name="minus" :size="14" /></button>
                                    <span x-text="item.qty"></span>
                                    <button type="button" @click="$store.cart.update(item.key, item.qty + 1)" :disabled="item.product.max_qty && item.qty >= item.product.max_qty" aria-label="Increase"><x-ico name="plus" :size="14" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Recommendations --}}
            <template x-if="$store.cart.recommendations.length">
                <div>
                    <x-section-head title="You might also need" />
                    <div x-data="rail()" class="relative"><div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0"><template x-for="p in $store.cart.recommendations" :key="p.id"><div class="w-40 shrink-0 sm:w-44" data-slide>@include('partials.card-dynamic')</div></template></div></div>
                </div>
            </template>
        </div>

        {{-- Bill --}}
        <aside class="lg:col-span-4">
            <div class="space-y-4 lg:sticky lg:top-[7.5rem]">
                <div class="card p-4">
                    <p class="text-sm font-extrabold">Have a promo code?</p>
                    <form @submit.prevent="$store.cart.applyCoupon(code)" class="mt-2 flex gap-2" x-show="!$store.cart.coupon">
                        <input x-model="code" placeholder="Enter code" class="field h-10 flex-1 uppercase" aria-label="Promo code">
                        <button type="submit" :disabled="$store.cart.couponBusy" class="btn btn-outline h-10">Apply</button>
                    </form>
                    <p x-show="$store.cart.couponError" x-cloak class="error-text" x-text="$store.cart.couponError"></p>
                    <div x-show="$store.cart.coupon" x-cloak class="mt-2 flex items-center justify-between rounded-lg bg-leaf-light px-3 py-2 text-sm">
                        <span class="font-bold text-leaf-dark"><span x-text="$store.cart.coupon?.code"></span> applied <span class="font-normal" x-text="'· ' + ($store.cart.coupon?.label || '')"></span></span>
                        <button type="button" @click="$store.cart.removeCoupon()" class="text-xs font-semibold text-slate hover:text-berry">Remove</button>
                    </div>
                </div>

                <div class="card p-4">
                    <p class="text-sm font-extrabold">Bill details</p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate">Item total</dt><dd class="tabular" x-text="$store.cart.subtotal_formatted"></dd></div>
                        <div class="flex justify-between" x-show="$store.cart.discount > 0"><dt class="text-slate">Coupon discount</dt><dd class="tabular text-leaf" x-text="'− ' + $store.cart.discount_formatted"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate">Delivery charge</dt><dd class="tabular" :class="$store.cart.shipping === 0 && 'font-bold text-leaf'" x-text="$store.cart.shipping === 0 ? 'FREE' : $store.cart.shipping_formatted"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate" x-text="'Taxes (' + $store.cart.tax_label + ')'"></dt><dd class="tabular" x-text="$store.cart.tax_formatted"></dd></div>
                        <div class="flex justify-between border-t border-line pt-3 text-base font-extrabold"><dt>To pay</dt><dd class="tabular" x-text="$store.cart.total_formatted"></dd></div>
                    </dl>
                    <a href="{{ route('checkout') }}" class="btn btn-primary btn-lg btn-block mt-4">Proceed to checkout <x-ico name="arrow-right" :size="16" /></a>
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-slate"><x-ico name="lock" :size="12" /> Safe & secure payments · UPI, cards, wallets, COD</p>
                </div>
            </div>
        </aside>
    </div>
</section>
@endsection
