@extends('layouts.app')

@section('content')
@php
    $card = $product->toCard();
    $shippingNote = '<p>Complimentary standard delivery on orders over '.money((int) setting('site.free_shipping_threshold', 0)).'; express delivery available at checkout. Orders placed before 14:00 IST ship the same business day.</p>';
    $returnsNote = '<p>30 days to return or exchange, collected from your door at no charge. Pieces must be unworn with tags attached. Fragrances and earrings are final sale once opened. <a href="/returns">Read the full policy</a>.</p>';
    $accordion = [
        'description' => ['Description', $product->details ?: '<p>'.e($product->description).'</p>'],
        'details' => ['Details', $product->sku || $product->brand ? '<ul>'.($product->brand ? '<li>Brand: '.e($product->brand).'</li>' : '').($product->sku ? '<li>Reference: '.e($product->sku).'</li>' : '').($product->material ? '<li>Primary material: '.e($product->material).'</li>' : '').(!empty($product->colors) ? '<li>Colours: '.e(implode(', ', array_column($product->colors, 'name'))).'</li>' : '').'</ul>' : null],
        'materials' => ['Materials', $product->materials ? '<p>'.e($product->materials).'</p>' : null],
        'care' => ['Care', $product->care ? '<p>'.e($product->care).'</p>' : null],
        'shipping' => ['Shipping', $shippingNote],
        'returns' => ['Returns', $returnsNote],
    ];
@endphp
<section class="container-luxe pt-[4.5rem] lg:pt-40" x-data="productPage(@js($card))">
    <nav aria-label="Breadcrumb" class="mb-8 hidden flex-wrap items-center gap-2 lg:flex text-[0.625rem] uppercase tracking-[0.2em] text-taupe">
        <a href="{{ route('home') }}" class="hover:underline">Home</a><span>/</span>
        <a href="{{ route('shop.index') }}" class="hover:underline">Shop</a><span>/</span>
        @if($product->category?->parent)<a href="{{ $product->category->parent->url }}" class="hover:underline">{{ $product->category->parent->name }}</a><span>/</span>@endif
        @if($product->category)<a href="{{ $product->category->url }}" class="hover:underline">{{ $product->category->name }}</a><span>/</span>@endif
        <span class="text-ink">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">
        {{-- Gallery --}}
        <div class="lg:col-span-7">
            <div class="grid gap-3 lg:grid-cols-[5rem_1fr]">
                <div class="no-scrollbar order-2 flex gap-3 overflow-x-auto lg:order-1 lg:flex-col lg:overflow-visible">
                    <template x-for="(s, i) in slides" :key="i">
                        <button type="button" @click="image = i" class="relative aspect-[3/4] w-16 shrink-0 overflow-hidden border bg-sand transition-colors lg:w-full" :class="image === i ? 'border-ink' : 'border-transparent opacity-70 hover:opacity-100'" :aria-label="`Slide ${i + 1}`">
                            <template x-if="s.type === 'image'"><img :src="s.src" alt="" class="img-cover"></template>
                            <template x-if="s.type === 'video'"><span class="absolute inset-0 grid place-items-center bg-ink text-ivory"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></template>
                        </button>
                    </template>
                </div>
                <div x-ref="stage" class="order-1 relative aspect-[3/4] overflow-hidden bg-sand lg:order-2">
                    <template x-for="(s, i) in slides" :key="'m' + i">
                        <div x-show="image === i" class="absolute inset-0">
                            <template x-if="s.type === 'image'">
                                <div class="relative h-full w-full cursor-zoom-in" @mouseenter="zoom.on = true" @mouseleave="zoom.on = false" @mousemove="move($event)" @click="lightbox = true">
                                    <img :src="s.src" :alt="p.name + ' — image ' + (i + 1)" class="img-cover transition-transform duration-300" :style="zoom.on ? `transform: scale(1.8); transform-origin: ${zoom.x}% ${zoom.y}%` : ''">
                                </div>
                            </template>
                            <template x-if="s.type === 'video' && !isYouTube(s.src)"><video :src="s.src" controls playsinline class="h-full w-full object-cover"></video></template>
                            <template x-if="s.type === 'video' && isYouTube(s.src)"><iframe :src="embed(s.src)" class="h-full w-full" allow="autoplay; encrypted-media" allowfullscreen title="Product video"></iframe></template>
                        </div>
                    </template>
                    <button type="button" @click="prev()" class="absolute left-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-ivory/80 backdrop-blur lg:hidden" aria-label="Previous image"><x-ico name="arrow-left" :size="16" /></button>
                    <button type="button" @click="next()" class="absolute right-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-ivory/80 backdrop-blur lg:hidden" aria-label="Next image"><x-ico name="arrow-right" :size="16" /></button>
                    <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5 lg:hidden"><template x-for="(s, i) in slides" :key="'d' + i"><span class="h-1.5 w-1.5 rounded-full" :class="image === i ? 'bg-ink' : 'bg-ink/25'"></span></template></div>
                    @if($product->is_new)<span class="absolute left-4 top-4 bg-ivory/90 px-2.5 py-1 text-[0.5625rem] uppercase tracking-[0.2em]">New</span>@endif
                </div>
            </div>
        </div>

        {{-- Purchase panel --}}
        <div class="lg:col-span-5">
            <div class="lg:sticky lg:top-28">
                <p class="eyebrow text-taupe">{{ $product->brand ?: $product->category?->name }}@if($product->brand && $product->category) <span class="text-taupe/60">·</span> {{ $product->category->name }}@endif</p>
                <h1 class="mt-3 font-serif text-4xl leading-tight lg:text-5xl">{{ $product->name }}</h1>
                <div class="mt-4 flex items-center gap-4">
                    <a href="#reviews" class="flex items-center gap-2"><x-rating :value="$product->rating" :count="$product->review_count" :size="11" /></a>
                    @if($product->sku)<span class="text-[0.6875rem] text-taupe">Ref. {{ $product->sku }}</span>@endif
                </div>
                <div class="mt-5 flex items-baseline gap-3 text-xl tabular-nums">
                    <span class="{{ $product->is_on_sale ? 'text-rouge' : '' }}">{{ money($product->price) }}</span>
                    @if($product->compare_at_price)<span class="text-sm text-smoke line-through">{{ money($product->compare_at_price) }}</span><span class="text-[0.625rem] uppercase tracking-[0.2em] text-rouge">−{{ $product->discount_percent }}%</span>@endif
                </div>
                <p class="mt-6 text-[0.9375rem] leading-relaxed text-smoke">{{ $product->description }}</p>

                @if(!empty($product->colors))
                    <div class="mt-8">
                        <p class="mb-3 text-[0.6875rem] uppercase tracking-[0.2em]">Colour <span class="ml-2 text-smoke normal-case tracking-normal" x-text="color"></span></p>
                        <div class="flex gap-3">
                            @foreach($product->colors as $c)
                                <button type="button" @click="color = @js($c['name'])" aria-label="{{ $c['name'] }}" :aria-pressed="color === @js($c['name'])" class="h-8 w-8 rounded-full ring-1 ring-offset-2 ring-offset-ivory transition-all" :class="color === @js($c['name']) ? 'ring-ink' : 'ring-ink/15 hover:ring-ink/40'" style="background-color: {{ $c['hex'] }}"></button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-8" x-ref="sizes">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-[0.6875rem] uppercase tracking-[0.2em]">{{ str_contains($product->sizes[0] ?? '', 'ml') ? 'Size' : (count($product->sizes ?? []) === 1 ? 'Size' : 'Select size') }} <span x-show="sizeError && !size" x-cloak class="ml-2 normal-case tracking-normal text-rouge">— please choose a size</span></p>
                        <a href="/size-guide" class="link-underline text-[0.6875rem] text-smoke">Size guide</a>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($product->sizes ?? [] as $s)
                            <button type="button" @click="size = @js($s); sizeError = false" :aria-pressed="size === @js($s)" class="min-w-14 border px-4 py-3 text-[0.75rem] uppercase tracking-wider transition-colors" :class="size === @js($s) ? 'border-ink bg-ink text-ivory' : 'border-ink/20 hover:border-ink'">{{ $s }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8 flex gap-3" x-ref="buy">
                    <div class="flex h-14 items-center border border-ink/20">
                        <button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity" class="grid h-full w-12 place-items-center hover:bg-sand"><x-ico name="minus" :size="14" :stroke="1.5" /></button>
                        <span class="w-8 text-center text-sm tabular-nums" x-text="qty"></span>
                        <button type="button" @click="qty++" aria-label="Increase quantity" class="grid h-full w-12 place-items-center hover:bg-sand"><x-ico name="plus" :size="14" :stroke="1.5" /></button>
                    </div>
                    <button type="button" @click="add()" :disabled="$store.cart.busy || !p.in_stock" class="btn btn-primary btn-lg flex-1">{{ $product->in_stock ? 'Add to Bag' : 'Sold out' }}</button>
                    <button type="button" @click="$store.wishlist.toggle(p.id)" aria-label="Add to wishlist" :aria-pressed="$store.wishlist.has(p.id)" class="grid h-14 w-14 shrink-0 place-items-center border border-ink/20 transition-colors hover:border-ink"><x-ico name="heart" :size="16" :stroke="1.5" ::class="$store.wishlist.has(p.id) && 'fill-ink'" /></button>
                </div>
                @if($product->in_stock)<button type="button" @click="add(true)" :disabled="$store.cart.busy" class="btn btn-outline btn-lg mt-3 w-full">Buy now</button>@endif
                <p class="mt-4 flex items-center gap-2 text-[0.75rem] {{ $product->in_stock ? 'text-smoke' : 'text-rouge' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $product->in_stock ? 'bg-emerald-600' : 'bg-rouge' }}"></span>
                    {{ $product->in_stock ? ($product->stock <= 5 ? "Only {$product->stock} left — ships within 2 business days" : 'In stock — ships within 2 business days') : 'Currently sold out' }}
                </p>

                {{-- Accordion --}}
                <div class="mt-10 divide-y divide-ink/10 border-y border-ink/10">
                    @foreach($accordion as $key => [$label, $body])
                        @continue(blank($body))
                        <div>
                            <button type="button" @click="open = open === '{{ $key }}' ? null : '{{ $key }}'" :aria-expanded="open === '{{ $key }}'" class="flex w-full items-center justify-between py-4 text-[0.6875rem] uppercase tracking-[0.2em]">
                                {{ $label }}
                                <x-ico name="plus" :size="14" x-show="open !== '{{ $key }}'" />
                                <x-ico name="minus" :size="14" x-show="open === '{{ $key }}'" x-cloak />
                            </button>
                            <div x-show="open === '{{ $key }}'" x-collapse x-cloak><div class="prose-luxe pb-5">{!! $body !!}</div></div>
                        </div>
                    @endforeach
                </div>

                <ul class="mt-6 grid grid-cols-3 gap-4 text-[0.625rem] uppercase tracking-[0.15em] text-smoke">
                    <li class="flex items-center gap-2"><x-ico name="badge-check" :size="16" :stroke="1" /> Authentic</li>
                    <li class="flex items-center gap-2"><x-ico name="truck" :size="16" :stroke="1" /> Tracked delivery</li>
                    <li class="flex items-center gap-2"><x-ico name="rotate" :size="16" :stroke="1" /> 30-day returns</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Sticky mobile add-to-bag --}}
    <div x-cloak x-show="showSticky" x-transition class="above-tabs border-t border-ink/10 bg-ivory/95 px-5 py-3 backdrop-blur lg:hidden">
        <div class="flex items-center gap-4">
            <div class="min-w-0 flex-1">
                <p class="truncate font-serif text-base">{{ $product->name }}</p>
                <p class="text-xs tabular-nums text-smoke">{{ money($product->price) }} <span x-show="size">· <span x-text="size"></span></span></p>
            </div>
            <button type="button" @click="add()" class="btn btn-primary">Add to Bag</button>
        </div>
    </div>

    {{-- Lightbox --}}
    <div x-cloak x-show="lightbox" class="fixed inset-0 z-[90] bg-ink/95" x-transition.opacity @keydown.window.escape="lightbox = false">
        <button type="button" @click="lightbox = false" class="absolute right-5 top-5 grid h-11 w-11 place-items-center text-ivory" aria-label="Close"><x-ico name="close" :size="24" /></button>
        <button type="button" @click="prev()" class="absolute left-4 top-1/2 grid h-12 w-12 -translate-y-1/2 place-items-center text-ivory" aria-label="Previous"><x-ico name="arrow-left" :size="22" /></button>
        <button type="button" @click="next()" class="absolute right-4 top-1/2 grid h-12 w-12 -translate-y-1/2 place-items-center text-ivory" aria-label="Next"><x-ico name="arrow-right" :size="22" /></button>
        <div class="flex h-full items-center justify-center p-6 lg:p-16">
            <template x-if="current && current.type === 'image'"><img :src="current.src" :alt="p.name" class="max-h-full max-w-full object-contain"></template>
            <template x-if="current && current.type === 'video' && !isYouTube(current.src)"><video :src="current.src" controls autoplay class="max-h-full max-w-full"></video></template>
            <template x-if="current && current.type === 'video' && isYouTube(current.src)"><iframe :src="embed(current.src)" class="aspect-video w-full max-w-5xl" allowfullscreen title="Product video"></iframe></template>
        </div>
    </div>
</section>

{{-- Reviews --}}
<section id="reviews" class="container-luxe py-20 lg:py-28" x-data="reviewForm">
    <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">
        <div class="lg:col-span-4">
            <p class="eyebrow text-taupe">Reviews</p>
            <h2 class="display-md mt-4">{{ $product->review_count ? number_format($product->rating, 1) : 'No reviews yet' }}</h2>
            @if($product->review_count)
                <x-rating :value="$product->rating" :size="14" class="mt-2" />
                <p class="mt-2 text-sm text-smoke">Based on {{ $product->review_count }} {{ \Illuminate\Support\Str::plural('review', $product->review_count) }}</p>
                <ul class="mt-6 space-y-2">
                    @for($star = 5; $star >= 1; $star--)
                        @php $n = $breakdown[$star] ?? 0; $pct = $product->review_count ? round($n / $product->review_count * 100) : 0; @endphp
                        <li class="flex items-center gap-3 text-xs"><span class="w-10 tabular-nums text-smoke">{{ $star }} ★</span><span class="h-px flex-1 bg-ink/10"><span class="block h-px bg-ink" style="width: {{ $pct }}%"></span></span><span class="w-8 text-right tabular-nums text-smoke">{{ $n }}</span></li>
                    @endfor
                </ul>
            @else
                <p class="mt-3 text-sm text-smoke">Be the first to share your thoughts.</p>
            @endif
            <button type="button" @click="open = !open" class="btn btn-outline mt-8">Write a review</button>

            @if(session('review_status'))<p class="mt-4 text-sm text-emerald-700">{{ session('review_status') }}</p>@endif

            <form x-show="open" x-collapse x-cloak method="post" action="{{ route('products.reviews.store', $product->slug) }}" class="mt-8 space-y-5 border-t border-ink/10 pt-8">
                @csrf
                <div>
                    <p class="mb-2 text-[0.6875rem] uppercase tracking-[0.2em]">Your rating</p>
                    <div class="flex gap-1" @mouseleave="hover = 0">
                        <template x-for="i in 5" :key="i"><button type="button" @click="rating = i" @mouseenter="hover = i" class="text-2xl leading-none" :class="(hover || rating) >= i ? 'text-ink' : 'text-ink/20'" :aria-label="`${i} stars`">★</button></template>
                    </div>
                    <input type="hidden" name="rating" :value="rating">
                </div>
                <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em]">Name</span><input name="name" required value="{{ old('name', auth()->user()?->name) }}" class="input-luxe"></label>
                <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em]">Email (not published)</span><input name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" class="input-luxe"></label>
                <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em]">Title</span><input name="title" value="{{ old('title') }}" class="input-luxe"></label>
                <label class="block"><span class="text-[0.6875rem] uppercase tracking-[0.2em]">Review</span><textarea name="body" required rows="4" class="input-luxe">{{ old('body') }}</textarea></label>
                @error('body')<p class="text-sm text-rouge">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-primary">Submit review</button>
            </form>
        </div>

        <div class="lg:col-span-8">
            @forelse($reviews as $review)
                <article class="border-b border-ink/10 py-7 first:pt-0">
                    <div class="flex items-center justify-between gap-4">
                        <x-rating :value="$review->rating" :size="11" />
                        <span class="text-[0.625rem] uppercase tracking-[0.2em] text-taupe">{{ $review->created_at->format('d M Y') }}</span>
                    </div>
                    @if($review->title)<h3 class="mt-3 font-serif text-2xl">{{ $review->title }}</h3>@endif
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-smoke">{{ $review->body }}</p>
                    <p class="mt-3 text-[0.6875rem] uppercase tracking-[0.15em]">{{ $review->name }} <span class="ml-2 text-emerald-700">✓ Verified</span></p>
                </article>
            @empty
                <div class="border border-dashed border-ink/15 p-10 text-center text-sm text-smoke">Reviews appear here once approved.</div>
            @endforelse
        </div>
    </div>
</section>

{{-- Complete the look --}}
@if($completeTheLook->isNotEmpty())
    <section class="border-t border-ink/10 bg-cream">
        <div class="container-luxe py-20 lg:py-28">
            <x-section-header eyebrow="Styled with" title="Complete the look" :description="'Pieces from the same edit as the ' . $product->name . '.'" />
            <div class="mt-12 grid grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-4 lg:gap-x-6">
                @foreach($completeTheLook as $p)<x-product-card :product="$p" />@endforeach
            </div>
        </div>
    </section>
@endif

{{-- You may also like --}}
@if($related->isNotEmpty())
    <section class="container-luxe py-20 lg:py-28">
        <x-section-header eyebrow="Similar pieces" title="You may also like" :cta="'More ' . strtolower($product->category?->name ?? 'pieces')" :cta-url="$product->category?->url ?? route('shop.index')" />
        <div class="mt-12 grid grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-4 lg:gap-x-6">
            @foreach($related as $p)<x-product-card :product="$p" />@endforeach
        </div>
    </section>
@endif

{{-- Recently viewed (client-side) --}}
<section class="container-luxe pb-20 lg:pb-28" x-data="productRail('recent')" data-exclude="{{ $product->slug }}" x-show="items.length" x-cloak>
    <x-section-header eyebrow="Your history" title="Recently viewed" />
    <div class="mt-12 grid grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-4 lg:gap-x-6">
        <template x-for="p in items.slice(0, 4)" :key="p.id">
            <a :href="p.url" class="group block">
                <div class="relative aspect-[3/4] overflow-hidden bg-sand"><img :src="p.images[0]" :alt="p.name" class="img-cover img-zoom"></div>
                <p class="mt-4 text-[0.5625rem] uppercase tracking-[0.2em] text-taupe" x-text="p.category"></p>
                <p class="mt-1 font-serif text-[1.0625rem] leading-snug" x-text="p.name"></p>
                <p class="mt-1 text-sm tabular-nums" x-text="p.price_formatted"></p>
            </a>
        </template>
    </div>
</section>
@endsection
