{{-- Quick view modal (loads /api/products/{slug}) --}}
<div x-data="quickView()" x-show="$store.ui.quickView || $store.ui.quickViewLoading" x-cloak class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-6" @keydown.escape.window="$store.ui.closeAll()">
    <div class="overlay !z-0" x-show="$store.ui.quickView || $store.ui.quickViewLoading" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <div x-show="$store.ui.quickViewLoading && !p" class="relative z-10 rounded-full bg-warm px-5 py-3 text-sm font-medium text-muted shadow-float">Loading…</div>
    <template x-if="p">
        <div class="relative z-10 grid w-full max-h-[92dvh] overflow-y-auto rounded-t-2xl bg-warm shadow-float sm:max-w-3xl sm:grid-cols-2 sm:rounded-2xl" x-transition x-trap.noscroll="!!p">
            <button type="button" @click="$store.ui.closeAll()" class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-ink shadow-sm hover:text-red" aria-label="Close"><x-ico name="close" :size="18" /></button>
            <div class="bg-cream p-4 sm:p-6">
                <div class="aspect-square overflow-hidden rounded-xl bg-white"><img :src="p.images[image] || p.images[0]" :alt="p.name" class="img-contain"></div>
                <div class="mt-3 flex gap-2" x-show="p.images.length > 1"><template x-for="(src, i) in p.images" :key="src"><button type="button" @click="image = i" class="h-14 w-14 overflow-hidden rounded-lg border-2 bg-white" :class="image === i ? 'border-gold' : 'border-transparent'"><img :src="src" alt="" class="img-cover"></button></template></div>
            </div>
            <div class="p-5 sm:p-7">
                <p class="eyebrow" x-text="p.brand || p.category"></p>
                <h2 class="mt-2 font-serif text-2xl leading-tight" x-text="p.name"></h2>
                <p class="mt-1 flex items-center gap-2 text-xs text-muted"><span class="text-star" x-text="'★'.repeat(Math.round(p.rating || 0))"></span><span x-text="p.review_count ? p.rating.toFixed(1) + ' (' + p.review_count.toLocaleString('en-IN') + ')' : 'New'"></span></p>
                <p class="mt-3 text-sm leading-relaxed text-muted line-clamp-2" x-text="p.description"></p>
                <div class="mt-4 flex flex-wrap items-end gap-2"><span class="font-serif text-2xl font-semibold tabular" x-text="p.price_formatted"></span><span x-show="p.compare_at_price_formatted" class="text-sm text-muted strike" x-text="p.compare_at_price_formatted"></span><span x-show="p.discount_percent > 0" class="badge badge-soft" x-text="p.discount_percent + '% off'"></span></div>
                <div class="mt-4" x-show="p.sizes.length > 1"><p class="label">Pack size</p><div class="flex flex-wrap gap-2"><template x-for="s in p.sizes" :key="s"><button type="button" @click="size = s" class="chip" :class="size === s && 'chip-active'" x-text="s"></button></template></div></div>
                <div class="mt-5 flex items-center gap-3">
                    <div class="stepper"><button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span x-text="qty"></span><button type="button" @click="qty = Math.min(p.max_qty || 20, qty + 1)" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div>
                    <button type="button" @click="add()" :disabled="!p.in_stock" class="btn btn-primary flex-1"><span x-text="p.in_stock ? 'Add to cart' : 'Out of stock'"></span></button>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm">
                    <button type="button" @click="add(true)" :disabled="!p.in_stock" class="font-semibold text-red hover:underline">Buy now</button>
                    <a :href="p.url" class="text-muted hover:text-red">View full details →</a>
                </div>
            </div>
        </div>
    </template>
</div>
