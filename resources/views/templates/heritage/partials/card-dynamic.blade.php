{{-- Client-rendered product card for JS-driven lists. Expects `p` in scope. --}}
<article class="pcard h-full" x-data="hCard(p)">
    <div class="pcard-media">
        <a :href="p.url"><img :src="p.images[0]" :alt="p.name" loading="lazy"></a>
        <span x-show="p.discount_percent > 0" class="pcard-off" x-text="p.discount_percent + '% OFF'"></span>
        <button type="button" @click="$store.wishlist.toggle(p.id)" class="absolute right-2.5 top-2.5 grid h-8 w-8 place-items-center rounded-full bg-white/95 text-muted shadow-sm hover:text-red" :class="$store.wishlist.has(p.id) && '!text-red'" aria-label="Wishlist"><x-ico name="heart" :size="15" ::class="$store.wishlist.has(p.id) && 'fill-current'" /></button>
    </div>
    <div class="pcard-body">
        <h3 class="pcard-name"><a :href="p.url" class="hover:text-red" x-text="p.name"></a></h3>
        <p class="pcard-price"><span class="tabular" x-text="p.price_formatted"></span><s x-show="p.compare_at_price_formatted" class="tabular" x-text="p.compare_at_price_formatted"></s></p>
        <template x-if="p.sizes.length > 1"><select class="pcard-select" x-model="size" aria-label="Pack size"><template x-for="s in p.sizes" :key="s"><option :value="s" x-text="s"></option></template></select></template>
        <template x-if="p.sizes.length <= 1"><span class="pcard-select truncate text-muted" x-text="p.sizes[0] || p.category"></span></template>
        <button type="button" @click="add()" class="pcard-btn" :disabled="!p.in_stock" :class="added && '!bg-leaf'"><span x-text="!p.in_stock ? 'Sold out' : (added ? 'Added ✓' : 'Add to Cart')"></span></button>
    </div>
</article>
