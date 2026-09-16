@props(['product', 'showRating' => false, 'priority' => false])
@php
    $card = $product->toCard();
    $discount = $product->discount_percent;
    $singleSize = count($product->sizes ?? []) === 1;
@endphp
<article {{ $attributes->class('group relative flex flex-col') }} x-data="productCard(@js($card))">
    {{-- Media --}}
    <div class="relative aspect-[3/4] overflow-hidden bg-sand">
        <a href="{{ $product->url }}" aria-label="{{ $product->name }}" class="absolute inset-0 block">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" @if(!$priority) loading="lazy" @endif decoding="async"
                 class="img-cover img-zoom transition-opacity duration-700 {{ $product->hover_image_url ? 'group-hover:opacity-0' : '' }}">
            @if($product->hover_image_url)
                <img src="{{ $product->hover_image_url }}" alt="" loading="lazy" decoding="async"
                     class="img-cover absolute inset-0 scale-[1.04] opacity-0 transition-all duration-1000 ease-[var(--ease-luxe)] group-hover:scale-100 group-hover:opacity-100">
            @endif
        </a>

        {{-- Badges --}}
        <div class="pointer-events-none absolute left-3 top-3 flex flex-col gap-1.5">
            @if($product->is_new)
                <span class="bg-ivory/90 px-2.5 py-1 text-[0.5625rem] uppercase tracking-[0.2em] text-ink backdrop-blur">New</span>
            @endif
            @if($discount > 0)
                <span class="bg-ink px-2.5 py-1 text-[0.5625rem] uppercase tracking-[0.2em] text-ivory">−{{ $discount }}%</span>
            @endif
        </div>

        {{-- Wishlist --}}
        <button type="button"
                :aria-label="$store.wishlist.has(product.id) ? 'Remove from wishlist' : 'Add to wishlist'"
                :aria-pressed="$store.wishlist.has(product.id)"
                @click="$store.wishlist.toggle(product.id)"
                class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-ivory/85 text-ink backdrop-blur transition-all duration-500 lg:translate-y-1 lg:opacity-0 lg:group-hover:translate-y-0 lg:group-hover:opacity-100"
                :class="$store.wishlist.has(product.id) && 'lg:translate-y-0 lg:opacity-100'">
            <x-ico name="heart" :size="15" :stroke="1.5" ::class="$store.wishlist.has(product.id) && 'fill-ink'" />
        </button>

        {{-- Hover actions (desktop) --}}
        <div class="absolute inset-x-0 bottom-0 hidden translate-y-full transition-transform duration-500 ease-[var(--ease-luxe)] group-hover:translate-y-0 lg:block" :class="sizesOpen && 'translate-y-0'">
            <div class="mx-3 mb-3 bg-ivory/95 backdrop-blur-sm">
                <div x-show="sizesOpen && !singleSize" x-cloak class="flex flex-wrap items-center gap-1 p-2">
                    <template x-for="s in product.sizes" :key="s">
                        <button type="button" @click="add(s)" class="min-w-9 px-2 py-2 text-[0.6875rem] uppercase tracking-wider transition-colors hover:bg-ink hover:text-ivory" x-text="s"></button>
                    </template>
                    <button type="button" @click="sizesOpen = false" class="ml-auto px-2 text-[0.625rem] uppercase tracking-widest text-smoke hover:text-ink">Close</button>
                </div>
                <div x-show="!(sizesOpen && !singleSize)" class="flex items-stretch divide-x divide-ink/10">
                    <button type="button" @click="quickAdd()" class="flex-1 py-3 text-[0.625rem] uppercase tracking-[0.2em] transition-colors hover:bg-ink hover:text-ivory" x-text="added ? 'Added ✓' : 'Quick Add'">Quick Add</button>
                    <button type="button" @click="$store.ui.showQuickView(product.slug)" aria-label="Quick view" class="grid w-12 place-items-center transition-colors hover:bg-ink hover:text-ivory">
                        <x-ico name="eye" :size="15" :stroke="1.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Info --}}
    <div class="flex flex-col gap-1.5 pt-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="eyebrow text-[0.5625rem] text-taupe">{{ $product->category?->name }}</p>
                <h3 class="mt-1 font-serif text-[1.0625rem] leading-snug text-ink">
                    <a href="{{ $product->url }}" class="line-clamp-2 hover:text-smoke lg:line-clamp-1">{{ $product->name }}</a>
                </h3>
            </div>
            @if(!empty($product->colors))
                <div class="mt-1 flex shrink-0 items-center gap-1.5" aria-label="Available colours">
                    @foreach(array_slice($product->colors, 0, 4) as $c)
                        <span title="{{ $c['name'] }}" class="h-3 w-3 rounded-full ring-1 ring-ink/10 ring-offset-1 ring-offset-ivory" style="background-color: {{ $c['hex'] }}"></span>
                    @endforeach
                    @if(count($product->colors) > 4)<span class="text-[0.625rem] text-smoke">+{{ count($product->colors) - 4 }}</span>@endif
                </div>
            @endif
        </div>
        <div class="flex items-center gap-2 text-[0.875rem] tabular-nums">
            <span class="{{ $discount > 0 ? 'text-rouge' : '' }}">{{ money($product->price) }}</span>
            @if($product->compare_at_price)<span class="text-smoke line-through">{{ money($product->compare_at_price) }}</span>@endif
        </div>
        @if($showRating)<x-rating :value="$product->rating" :count="$product->review_count" />@endif
    </div>

    {{-- Mobile quick add --}}
    <button type="button" @click="$store.ui.showQuickView(product.slug)" class="mt-3 border border-ink/15 py-2.5 text-[0.625rem] uppercase tracking-[0.2em] transition-colors active:bg-ink active:text-ivory lg:hidden">Quick Add</button>
</article>
