@props(['product', 'compact' => false, 'priority' => false])
@php
    $card = $product->toCard();
    $discount = $product->discount_percent;
    $unit = $product->unit ?? (count($product->sizes ?? []) > 1 ? count($product->sizes).' pack sizes' : null);
    $tags = array_slice($product->dietary_tags ?? [], 0, 2);
@endphp
<article {{ $attributes->class(['card card-hover group relative flex h-full flex-col overflow-hidden', 'w-[11.5rem] sm:w-56' => $compact]) }} x-data="hCard(@js($card))" data-slide>
    {{-- Media --}}
    <div class="relative bg-cream">
        <a href="{{ $product->url }}" class="block aspect-square overflow-hidden" aria-label="{{ $product->name }}">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" @if(!$priority) loading="lazy" @endif decoding="async" class="img-cover transition-transform duration-700 ease-[var(--ease-soft)] group-hover:scale-[1.04]">
        </a>
        <span class="absolute left-3 top-3 flex flex-col gap-1">
            @if($discount > 0)<span class="badge badge-off">{{ $discount }}% off</span>@endif
            @if($product->is_new)<span class="badge badge-gold">New</span>@endif
        </span>
        <button type="button" @click="$store.wishlist.toggle(product.id)" :aria-pressed="$store.wishlist.has(product.id)" class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white/95 text-muted shadow-sm transition-colors hover:text-red" :class="$store.wishlist.has(product.id) && '!text-red'" aria-label="Add to wishlist"><x-ico name="heart" :size="16" ::class="$store.wishlist.has(product.id) && 'fill-current'" /></button>
        <button type="button" @click="$store.ui.showQuickView(product.slug)" class="absolute bottom-3 left-1/2 hidden -translate-x-1/2 translate-y-2 items-center gap-1.5 rounded-full bg-maroon/90 px-3.5 py-1.5 text-xs font-semibold text-cream opacity-0 shadow-sm transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100 lg:inline-flex"><x-ico name="eye" :size="14" /> Quick view</button>
        @if(!$product->in_stock)<span class="absolute inset-0 grid place-items-center bg-white/70 text-xs font-bold uppercase tracking-wider text-muted">Out of stock</span>@endif
    </div>

    {{-- Info --}}
    <div class="flex flex-1 flex-col p-3.5">
        <div class="flex items-center gap-1.5 text-[0.6875rem] font-medium text-muted">
            @if($product->is_veg !== null)<span class="veg-mark {{ $product->is_veg ? '' : 'nonveg' }}" title="{{ $product->is_veg ? 'Vegetarian' : 'Non-vegetarian' }}"></span>@endif
            <span class="truncate">{{ $product->brand ?: $product->category?->name }}</span>
        </div>
        <h3 class="mt-1 font-serif text-[1.02rem] font-semibold leading-snug"><a href="{{ $product->url }}" class="line-clamp-2 hover:text-red">{{ $product->name }}</a></h3>
        @if(!$compact && $product->description)<p class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted">{{ $product->description }}</p>@endif
        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
            @if($unit)<span>{{ $unit }}</span>@endif
            @if($product->review_count > 0)<span class="flex items-center gap-1"><x-ico name="star" :size="11" class="fill-star text-star" /> {{ number_format($product->rating, 1) }} <span class="text-[0.6875rem]">({{ number_format($product->review_count) }})</span></span>@endif
        </div>
        @if($tags)<div class="mt-2 flex flex-wrap gap-1">@foreach($tags as $t)<span class="rounded-sm bg-cream px-1.5 py-0.5 text-[0.625rem] font-semibold uppercase tracking-wider text-maroon">{{ $t }}</span>@endforeach</div>@endif

        <div class="mt-auto pt-3">
            <div class="flex items-baseline gap-2">
                <span class="font-serif text-lg font-semibold tabular">{{ money($product->price) }}</span>
                @if($product->compare_at_price)<span class="text-xs text-muted tabular strike">{{ money($product->compare_at_price) }}</span><span class="text-[0.6875rem] font-semibold text-leaf">Save {{ money($product->compare_at_price - $product->price) }}</span>@endif
            </div>
            @if($product->in_stock)
                <div class="mt-2.5 flex items-center gap-2">
                    @unless($card['sizes'] && count($card['sizes']) > 1)
                        <div class="stepper !h-9 shrink-0"><button type="button" class="!w-8" @click="dec()" aria-label="Decrease quantity"><x-ico name="minus" :size="13" /></button><span class="!min-w-7 text-xs" x-text="qty"></span><button type="button" class="!w-8" @click="inc()" aria-label="Increase quantity"><x-ico name="plus" :size="13" /></button></div>
                    @endunless
                    <button type="button" @click="add()" class="btn btn-primary btn-sm h-9 flex-1 !px-2" :class="added && '!bg-leaf'"><span x-text="added ? 'Added ✓' : (multi ? 'Choose pack' : 'Add to cart')">Add to cart</span></button>
                </div>
            @else
                <button type="button" @click="$store.wishlist.toggle(product.id)" class="btn btn-ghost btn-sm mt-2.5 w-full">Notify me</button>
            @endif
        </div>
    </div>
</article>
