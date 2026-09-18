@props(['product', 'compact' => false, 'priority' => false])
@php
    $card = $product->toCard();
    $discount = $product->discount_percent;
    $unit = $product->unit ?? (count($product->sizes ?? []) > 1 ? count($product->sizes).' pack sizes' : null);
@endphp
<article {{ $attributes->class(['card card-hover relative flex h-full flex-col p-2.5', 'w-40 sm:w-44' => $compact]) }} x-data="gCard(@js($card))" data-slide>
    {{-- Media --}}
    <a href="{{ $product->url }}" class="relative block aspect-square overflow-hidden rounded-lg bg-paper" aria-label="{{ $product->name }}">
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" @if(!$priority) loading="lazy" @endif decoding="async" class="img-cover transition-transform duration-500 group-hover:scale-105">
        <span class="absolute left-1.5 top-1.5 flex flex-col gap-1">
            @if($discount > 0)<span class="badge badge-off">{{ $discount }}% OFF</span>@endif
            @if($product->is_new)<span class="badge badge-new">NEW</span>@endif
        </span>
        @if(!$product->in_stock)<span class="absolute inset-0 grid place-items-center bg-white/70 text-xs font-extrabold uppercase tracking-wider text-slate">Out of stock</span>@endif
    </a>
    <button type="button" @click="$store.wishlist.toggle(product.id)" :aria-pressed="$store.wishlist.has(product.id)" class="absolute right-4 top-4 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-slate shadow-sm hover:text-berry" :class="$store.wishlist.has(product.id) && 'text-berry'" aria-label="Save for later">
        <x-ico name="heart" :size="15" ::class="$store.wishlist.has(product.id) && 'fill-current'" />
    </button>

    {{-- Info --}}
    <div class="flex flex-1 flex-col pt-2.5">
        <div class="flex items-start gap-1.5">
            @if($product->is_veg !== null)<span class="veg-mark mt-0.5 {{ $product->is_veg ? '' : 'nonveg' }}" title="{{ $product->is_veg ? 'Vegetarian' : 'Non-vegetarian' }}"></span>@endif
            <p class="truncate text-[0.6875rem] font-semibold text-slate">{{ $product->brand ?: $product->category?->name }}</p>
        </div>
        <h3 class="mt-0.5 text-[0.8125rem] font-bold leading-snug">
            <a href="{{ $product->url }}" class="line-clamp-2 hover:text-leaf">{{ $product->name }}</a>
        </h3>
        @if($unit)<p class="mt-0.5 text-xs text-slate">{{ $unit }}</p>@endif
        @if($product->review_count > 0)
            <p class="mt-1 flex items-center gap-1 text-[0.6875rem] font-semibold text-slate"><x-ico name="star" :size="11" class="fill-star text-star" /> {{ number_format($product->rating, 1) }} <span class="font-normal text-mist">({{ number_format($product->review_count) }})</span></p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-2 pt-2">
            <div class="min-w-0">
                <p class="text-sm font-extrabold tabular">{{ money($product->price) }}</p>
                @if($product->compare_at_price)<p class="text-xs text-mist tabular strike">{{ money($product->compare_at_price) }}</p>@endif
            </div>
            @if($product->in_stock)
                <div class="shrink-0">
                    <button type="button" x-show="!qty" @click="add()" class="btn btn-outline btn-sm w-[4.25rem] font-extrabold">ADD</button>
                    <div x-show="qty" x-cloak class="stepper">
                        <button type="button" @click="dec()" aria-label="Decrease quantity"><x-ico name="minus" :size="14" /></button>
                        <span x-text="qty"></span>
                        <button type="button" @click="inc()" aria-label="Increase quantity"><x-ico name="plus" :size="14" /></button>
                    </div>
                </div>
            @else
                <button type="button" @click="$store.wishlist.toggle(product.id); $store.ui.notify('We’ll save it to your list')" class="btn btn-ghost btn-sm text-xs">Notify</button>
            @endif
        </div>
    </div>
</article>
