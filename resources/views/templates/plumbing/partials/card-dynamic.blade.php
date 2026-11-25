{{-- Client-rendered product card for JS-driven lists (recently viewed, wishlist). Expects `p` in scope.
     On Saved items `p.saved` says what was saved (App\Support\SavedItems::card()): a size saved
     shows that size, and its heart is that size's. --}}
<article class="pcard relative" x-data="pCard(p)">
    <a :href="p.url" class="pcard-media block">
        <img :src="p.images[0]" :alt="p.name" loading="lazy">
        <span x-show="p.discount_percent > 0" class="badge badge-off absolute left-2 top-2" x-text="p.discount_percent + '% off'"></span>
    </a>
    <button type="button" @click="$store.wishlist.toggle(p.id, p.name, p.saved?.chosen)" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-white/95 text-slate shadow-sm hover:text-danger" :class="$store.wishlist.has(p.id, p.saved?.chosen) && 'text-danger'" aria-label="Save to wishlist"><x-ico name="heart" :size="16" /></button>
    <div class="pcard-body">
        <p class="pcard-brand" x-text="p.brand || p.category"></p>
        <h3 class="pcard-name"><a :href="p.url" x-text="p.name"></a></h3>
        <div class="pcard-spec"><span x-show="p.material" x-text="p.material"></span><span x-show="p.saved?.label" x-text="p.saved?.label"></span><span x-show="!p.saved?.label && p.sizes.length" x-text="p.sizes.length > 1 ? p.sizes.length + ' sizes' : p.sizes[0]"></span></div>
        <div class="pcard-price"><span class="price" x-text="p.price_formatted"></span><span x-show="p.compare_at_price_formatted" class="mrp" x-text="p.compare_at_price_formatted"></span><span x-show="p.discount_percent > 0" class="off" x-text="p.discount_percent + '% off'"></span></div>
        <div class="pcard-btn" x-show="p.in_stock">
            <button type="button" x-show="!qty" @click="add()" class="btn btn-primary btn-sm btn-block"><x-ico name="cart" :size="15" /> Add to cart</button>
            <div x-show="qty" x-cloak class="stepper w-full justify-between"><button type="button" @click="dec()" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span x-text="qty + ' in cart'"></span><button type="button" @click="inc()" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div>
        </div>
        <p x-show="!p.in_stock" class="pcard-btn text-center text-xs font-semibold text-slate" x-text="p.saved && !p.saved.available ? 'No longer available' : 'Out of stock'">Out of stock</p>
    </div>
</article>
