@extends('layouts.app')

@section('content')
<x-page-head title="Your cart" eyebrow="Shopping cart" :breadcrumbs="['Cart' => null]" />

<section class="h-container py-8" x-data="{ code: '' }">
    <div x-show="$store.cart.loaded && !$store.cart.items.length && !$store.saved.items.length" x-cloak>
        <x-empty-state icon="bag" title="Your cart is empty" text="Rice, dals, spices, ghee — everything a good kitchen needs is a click away.">
            <a href="{{ route('shop.index') }}" class="btn btn-primary btn-lg">Start shopping</a>
        </x-empty-state>
    </div>

    <div x-show="$store.cart.items.length || $store.saved.items.length" x-cloak class="grid gap-8 lg:grid-cols-12 lg:gap-10">
        <div class="space-y-6 lg:col-span-8">
            {{-- Free delivery progress --}}
            <div class="card flex items-center gap-4 p-4" x-show="$store.cart.items.length && $store.cart.free_shipping_threshold > 0">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-cream text-gold"><x-ico name="truck" :size="18" /></span>
                <div class="flex-1"><p class="text-sm" x-text="$store.cart.remaining > 0 ? 'Add ' + $store.cart.remaining_formatted + ' more for free delivery' : 'Free delivery unlocked on this order'"></p><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-line-soft"><div class="h-full rounded-full bg-gold transition-all duration-500" :style="`width:${Math.min(100, ($store.cart.progress || 0) * 100)}%`"></div></div></div>
            </div>

            <div class="card divide-y divide-line-soft" x-show="$store.cart.items.length">
                <div class="flex items-center justify-between px-5 py-3"><p class="font-serif text-lg">Items <span class="font-sans text-sm text-muted" x-text="'(' + $store.cart.count + ')'"></span></p><p class="text-xs font-semibold text-maroon" x-show="$store.cart.savings > 0">✦ Saving <span x-text="$store.cart.savings_formatted"></span></p></div>
                <template x-for="item in $store.cart.items" :key="item.key">
                    <div class="flex gap-4 px-5 py-5">
                        <a :href="item.product.url" class="h-24 w-24 shrink-0 overflow-hidden rounded-lg border border-line-soft bg-cream sm:h-28 sm:w-28"><img :src="item.product.images[0]" :alt="item.product.name" class="img-cover"></a>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-muted" x-text="item.product.brand || item.product.category"></p>
                                    <a :href="item.product.url" class="line-clamp-2 font-serif text-base font-semibold leading-snug hover:text-red" x-text="item.product.name"></a>
                                    <p class="mt-0.5 text-xs text-muted" x-text="item.size"></p>
                                </div>
                                <button type="button" @click="$store.cart.remove(item.key)" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted hover:bg-cream hover:text-red" aria-label="Remove"><x-ico name="trash" :size="16" /></button>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-baseline gap-2"><span class="font-serif text-lg font-semibold tabular" x-text="item.line_total_formatted"></span><span x-show="item.product.compare_at_price" class="text-xs text-muted strike" x-text="'₹' + (item.product.compare_at_price * item.qty).toLocaleString('en-IN')"></span><span class="text-xs text-muted" x-text="item.qty > 1 ? item.product.price_formatted + ' each' : ''"></span></div>
                                <div class="flex items-center gap-3">
                                    <div class="stepper !h-9"><button type="button" class="!w-9" @click="$store.cart.update(item.key, item.qty - 1)" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span class="!min-w-8 text-sm" x-text="item.qty"></span><button type="button" class="!w-9" @click="$store.cart.update(item.key, item.qty + 1)" :disabled="item.product.max_qty && item.qty >= item.product.max_qty" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div>
                                    <button type="button" @click="$store.saved.save(item)" class="hidden items-center gap-1 text-xs font-medium text-muted hover:text-red sm:flex"><x-ico name="bookmark" :size="14" /> Save for later</button>
                                </div>
                            </div>
                            <button type="button" @click="$store.saved.save(item)" class="mt-2 text-xs font-medium text-muted hover:text-red sm:hidden">Save for later</button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Saved for later --}}
            <div class="card" x-show="$store.saved.items.length">
                <div class="flex items-center justify-between px-5 py-3"><p class="font-serif text-lg">Saved for later <span class="font-sans text-sm text-muted" x-text="'(' + $store.saved.items.length + ')'"></span></p></div>
                <ul class="divide-y divide-line-soft border-t border-line-soft">
                    <template x-for="s in $store.saved.items" :key="s.id + s.size">
                        <li class="flex items-center gap-4 px-5 py-4">
                            <a :href="s.url" class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-line-soft bg-cream"><img :src="s.image" alt="" class="img-cover"></a>
                            <div class="min-w-0 flex-1"><a :href="s.url" class="line-clamp-1 text-sm font-semibold hover:text-red" x-text="s.name"></a><p class="text-xs text-muted" x-text="s.size + ' · ' + s.price_formatted"></p></div>
                            <button type="button" @click="$store.saved.moveToCart(s)" class="btn btn-outline btn-sm">Move to cart</button>
                            <button type="button" @click="$store.saved.remove(s)" class="grid h-8 w-8 place-items-center rounded-lg text-muted hover:text-red" aria-label="Remove"><x-ico name="close" :size="16" /></button>
                        </li>
                    </template>
                </ul>
            </div>

            <template x-if="$store.cart.recommendations.length">
                <div><x-section-head eyebrow="You may also like" title="Complete your pantry" class="!mb-5" /><div x-data="rail()" class="relative"><div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0"><template x-for="p in $store.cart.recommendations" :key="p.id"><div class="w-[11.5rem] shrink-0 sm:w-56" data-slide>@include('partials.card-dynamic')</div></template></div></div></div>
            </template>
        </div>

        {{-- Summary --}}
        <aside class="lg:col-span-4" x-show="$store.cart.items.length">
            <div class="space-y-4 lg:sticky lg:top-[8.5rem]">
                <div class="card p-5">
                    <p class="font-serif text-lg">Have a coupon?</p>
                    <form @submit.prevent="$store.cart.applyCoupon(code)" class="mt-3 flex gap-2" x-show="!$store.cart.coupon"><input x-model="code" placeholder="Enter code" class="field h-11 flex-1 uppercase" aria-label="Coupon code"><button type="submit" :disabled="$store.cart.couponBusy" class="btn btn-outline h-11">Apply</button></form>
                    <p x-show="$store.cart.couponError" x-cloak class="error-text" x-text="$store.cart.couponError"></p>
                    <div x-show="$store.cart.coupon" x-cloak class="mt-2 flex items-center justify-between rounded-lg bg-cream px-3 py-2 text-sm"><span class="font-semibold text-maroon"><span x-text="$store.cart.coupon?.code"></span> applied <span class="font-normal text-muted" x-text="'· ' + ($store.cart.coupon?.label || '')"></span></span><button type="button" @click="$store.cart.removeCoupon()" class="text-xs text-muted hover:text-red">Remove</button></div>
                </div>
                <div class="card p-5">
                    <p class="font-serif text-lg">Order summary</p>
                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="tabular" x-text="$store.cart.subtotal_formatted"></dd></div>
                        <div class="flex justify-between" x-show="$store.cart.discount > 0"><dt class="text-muted">Coupon discount</dt><dd class="tabular text-leaf" x-text="'− ' + $store.cart.discount_formatted"></dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Delivery <span class="text-xs" x-text="'(' + ($store.cart.shipping_method?.name || 'Standard') + ')'"></span></dt><dd class="tabular" x-text="$store.cart.shipping === 0 ? 'Free' : $store.cart.shipping_formatted"></dd></div>
                        <div class="flex justify-between"><dt class="text-muted" x-text="'Taxes (' + $store.cart.tax_label + ')'"></dt><dd class="tabular" x-text="$store.cart.tax_formatted"></dd></div>
                        <div class="flex justify-between border-t border-line pt-3 font-serif text-xl"><dt>Estimated total</dt><dd class="tabular font-semibold" x-text="$store.cart.total_formatted"></dd></div>
                    </dl>
                    <a href="{{ route('checkout') }}" class="btn btn-primary btn-lg btn-block mt-5">Proceed to checkout <x-ico name="arrow-right" :size="16" /></a>
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-muted"><x-ico name="lock" :size="12" /> Secure checkout · UPI, cards, wallets, COD</p>
                </div>
            </div>
        </aside>
    </div>
</section>
@endsection
