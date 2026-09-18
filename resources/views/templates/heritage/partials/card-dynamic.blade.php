{{-- Client-rendered product card for JS-driven lists. Expects `p` in scope. --}}
<article class="card card-hover group relative flex h-full flex-col overflow-hidden" x-data="hCard(p)">
    <div class="relative bg-cream">
        <a :href="p.url" class="block aspect-square overflow-hidden"><img :src="p.images[0]" :alt="p.name" loading="lazy" class="img-cover transition-transform duration-700 group-hover:scale-[1.04]"></a>
        <span x-show="p.discount_percent > 0" class="badge badge-off absolute left-3 top-3" x-text="p.discount_percent + '% off'"></span>
        <button type="button" @click="$store.wishlist.toggle(p.id)" class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white/95 text-muted shadow-sm hover:text-red" :class="$store.wishlist.has(p.id) && '!text-red'" aria-label="Wishlist"><x-ico name="heart" :size="16" ::class="$store.wishlist.has(p.id) && 'fill-current'" /></button>
    </div>
    <div class="flex flex-1 flex-col p-3.5">
        <p class="truncate text-[0.6875rem] font-medium text-muted" x-text="p.brand || p.category"></p>
        <h3 class="mt-1 font-serif text-[1.02rem] font-semibold leading-snug"><a :href="p.url" class="line-clamp-2 hover:text-red" x-text="p.name"></a></h3>
        <p class="mt-1 text-xs text-muted" x-text="p.unit || (p.sizes.length > 1 ? p.sizes.length + ' pack sizes' : '')"></p>
        <div class="mt-auto pt-3">
            <div class="flex items-baseline gap-2"><span class="font-serif text-lg font-semibold tabular" x-text="p.price_formatted"></span><span x-show="p.compare_at_price_formatted" class="text-xs text-muted strike" x-text="p.compare_at_price_formatted"></span></div>
            <div class="mt-2.5 flex items-center gap-2" x-show="p.in_stock">
                <div class="stepper !h-9 shrink-0" x-show="!multi"><button type="button" class="!w-8" @click="dec()" aria-label="Decrease"><x-ico name="minus" :size="13" /></button><span class="!min-w-7 text-xs" x-text="qty"></span><button type="button" class="!w-8" @click="inc()" aria-label="Increase"><x-ico name="plus" :size="13" /></button></div>
                <button type="button" @click="add()" class="btn btn-primary btn-sm h-9 flex-1 !px-2" :class="added && '!bg-leaf'"><span x-text="added ? 'Added ✓' : (multi ? 'Choose pack' : 'Add to cart')"></span></button>
            </div>
        </div>
    </div>
</article>
