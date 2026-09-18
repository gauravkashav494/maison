@props(['product', 'compact' => false, 'priority' => false])
@php $card = $product->toCard(); $discount = $product->discount_percent; @endphp
<article {{ $attributes->class(['pcard', 'w-[11.5rem] sm:w-[13.5rem] lg:w-[15.25rem]' => $compact]) }} x-data="hCard(@js($card))" data-slide>
    <div class="pcard-media">
        <a href="{{ $product->url }}" aria-label="{{ $product->name }}"><img src="{{ $product->image_url }}" alt="{{ $product->name }}" @if(!$priority) loading="lazy" @endif decoding="async"></a>
        @if($discount > 0)<span class="pcard-off">{{ $discount }}% OFF</span>@elseif($product->is_new)<span class="pcard-off">NEW</span>@endif
        <button type="button" @click="$store.wishlist.toggle(product.id)" :aria-pressed="$store.wishlist.has(product.id)" class="pcard-act absolute right-2.5 top-2.5 grid h-8 w-8 place-items-center rounded-full bg-white/95 text-muted shadow-sm hover:text-red" :class="$store.wishlist.has(product.id) && '!text-red is-on'" aria-label="Add to wishlist"><x-ico name="heart" :size="15" ::class="$store.wishlist.has(product.id) && 'fill-current'" /></button>
        <button type="button" @click="$store.ui.showQuickView(product.slug)" class="pcard-act absolute bottom-2.5 right-2.5 hidden h-8 w-8 place-items-center rounded-full bg-white/95 text-muted shadow-sm hover:text-red lg:grid" aria-label="Quick view"><x-ico name="eye" :size="15" /></button>
        @if(!$product->in_stock)<span class="absolute inset-x-0 bottom-0 bg-maroon-deep/80 py-1 text-center text-[0.6875rem] font-bold uppercase tracking-wider text-cream">Sold out</span>@endif
    </div>
    <div class="pcard-body">
        <h3 class="pcard-name"><a href="{{ $product->url }}" class="hover:text-red">{{ $product->name }}</a></h3>
        <p class="pcard-price"><span class="tabular">{{ money($product->price) }}</span>@if($product->compare_at_price)<s class="tabular">{{ money($product->compare_at_price) }}</s>@endif</p>
        @if(count($card['sizes']) > 1)
            <select class="pcard-select" x-model="size" aria-label="Pack size">@foreach($card['sizes'] as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select>
        @else
            <span class="pcard-select truncate text-muted">{{ $card['sizes'][0] ?? $product->category?->name }}</span>
        @endif
        @if($product->in_stock)
            <button type="button" @click="add()" class="pcard-btn" :class="added && '!bg-leaf'"><span x-text="added ? 'Added ✓' : 'Add to Cart'">Add to Cart</span></button>
        @else
            <button type="button" class="pcard-btn" disabled>Sold out</button>
        @endif
    </div>
</article>
