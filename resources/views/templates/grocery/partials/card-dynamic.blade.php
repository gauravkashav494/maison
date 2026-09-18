{{-- Client-rendered product card for JS-driven lists (recently viewed, wishlist). Expects `p` in scope. --}}
<article class="card card-hover relative flex h-full flex-col p-2.5" x-data="gCard(p)">
    <a :href="p.url" class="relative block aspect-square overflow-hidden rounded-lg bg-paper">
        <img :src="p.images[0]" :alt="p.name" loading="lazy" class="img-cover">
        <span x-show="p.discount_percent > 0" class="badge badge-off absolute left-1.5 top-1.5" x-text="p.discount_percent + '% OFF'"></span>
    </a>
    <button type="button" @click="$store.wishlist.toggle(p.id)" class="absolute right-4 top-4 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-slate shadow-sm hover:text-berry" :class="$store.wishlist.has(p.id) && 'text-berry'" aria-label="Save for later"><x-ico name="heart" :size="15" ::class="$store.wishlist.has(p.id) && 'fill-current'" /></button>
    <div class="flex flex-1 flex-col pt-2.5">
        <div class="flex items-start gap-1.5">
            <span x-show="p.is_veg !== null" class="veg-mark mt-0.5" :class="p.is_veg === false && 'nonveg'"></span>
            <p class="truncate text-[0.6875rem] font-semibold text-slate" x-text="p.brand || p.category"></p>
        </div>
        <h3 class="mt-0.5 text-[0.8125rem] font-bold leading-snug"><a :href="p.url" class="line-clamp-2 hover:text-leaf" x-text="p.name"></a></h3>
        <p class="mt-0.5 text-xs text-slate" x-text="p.unit || (p.sizes.length > 1 ? p.sizes.length + ' pack sizes' : '')"></p>
        <div class="mt-auto flex items-end justify-between gap-2 pt-2">
            <div><p class="text-sm font-extrabold tabular" x-text="p.price_formatted"></p><p x-show="p.compare_at_price_formatted" class="text-xs text-mist strike" x-text="p.compare_at_price_formatted"></p></div>
            <div class="shrink-0" x-show="p.in_stock">
                <button type="button" x-show="!qty" @click="add()" class="btn btn-outline btn-sm w-[4.25rem] font-extrabold">ADD</button>
                <div x-show="qty" x-cloak class="stepper"><button type="button" @click="dec()" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span x-text="qty"></span><button type="button" @click="inc()" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div>
            </div>
        </div>
    </div>
</article>
