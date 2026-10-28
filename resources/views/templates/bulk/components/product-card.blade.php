{{-- Server-rendered product card. Technical chips (material, size/pack) sit under the name; Add → stepper is synced to the cart. --}}
@props(['product', 'compact' => false])
@php
    $card = $product->toCard();
    $sizes = $product->sizes ?? [];
    $chips = array_values(array_filter([
        $product->material,
        count($sizes) === 1 ? $sizes[0] : (count($sizes) > 1 ? count($sizes).' sizes' : null),
    ]));
@endphp
<article class="pcard relative {{ $compact ? 'w-[11.5rem] shrink-0 sm:w-[13.5rem] lg:w-[15rem]' : '' }}" data-slide x-data="pCard(@js($card))">
    <a href="{{ $product->url }}" class="pcard-media block">
        @if($product->image_urls)<img src="{{ $product->image_urls[0] }}" alt="{{ $product->name }}" loading="lazy">@endif
        <div class="absolute left-2 top-2 flex flex-col gap-1">
            @if($product->discount_percent > 0)<span class="badge badge-off">{{ $product->discount_percent }}% off</span>@endif
            @if($product->is_best_seller)<span class="badge badge-best">Bestseller</span>@elseif($product->is_new)<span class="badge badge-new">New</span>@endif
        </div>
    </a>
    <button type="button" @click="$store.wishlist.toggle({{ $product->id }})" class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-white/95 text-slate shadow-sm hover:text-danger" :class="$store.wishlist.has({{ $product->id }}) && 'text-danger'" aria-label="Save to wishlist"><x-ico name="heart" :size="16" /></button>
    <div class="pcard-body">
        <p class="pcard-brand">{{ $product->brand ?: $product->category?->name }}</p>
        <h3 class="pcard-name"><a href="{{ $product->url }}">{{ $product->name }}</a></h3>
        @if($chips)<div class="pcard-spec">@foreach($chips as $chip)<span>{{ $chip }}</span>@endforeach</div>@endif
        @if($product->review_count)<div class="mt-1.5 flex items-center gap-1 text-xs text-slate"><x-rating :value="$product->rating" :size="12" /> <span class="font-semibold text-ink">{{ number_format($product->rating, 1) }}</span> ({{ number_format($product->review_count) }})</div>@endif
        <div class="pcard-price">
            <span class="price">{{ money($product->price) }}</span>
            @if($product->compare_at_price)<span class="mrp">{{ money($product->compare_at_price) }}</span><span class="off">{{ $product->discount_percent }}% off</span>@endif
        </div>
        <div class="pcard-btn">
            @if($product->in_stock)
                <button type="button" x-show="!qty" @click="add()" class="btn btn-primary btn-sm btn-block"><x-ico name="cart" :size="15" /> Add to cart</button>
                <div x-show="qty" x-cloak class="stepper w-full justify-between"><button type="button" @click="dec()" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span x-text="qty + ' in cart'"></span><button type="button" @click="inc()" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div>
            @else
                <p class="rounded-lg bg-canvas py-2 text-center text-xs font-semibold text-slate">Out of stock</p>
            @endif
        </div>
    </div>
</article>
