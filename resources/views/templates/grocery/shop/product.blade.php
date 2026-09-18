@extends('layouts.app')

@section('content')
@php
    $card = $product->toCard();
    $gs = tsetting('site');
    $crumbs = [];
    if ($product->category?->parent) { $crumbs[$product->category->parent->name] = $product->category->parent->url; }
    if ($product->category) { $crumbs[$product->category->name] = $product->category->url; }
    $crumbs[$product->name] = null;
    $facts = array_filter([
        'Brand' => $product->brand,
        'Shelf life' => $product->shelf_life,
        'Country of origin' => $product->country_of_origin,
        'Reference' => $product->sku,
    ]);
    $threshold = (int) setting('site.free_shipping_threshold', 0);
@endphp
<div x-data="productPage(@js($card))">
    <div class="g-container pt-4">
        <x-breadcrumbs :items="$crumbs" />
    </div>

    <section class="g-container grid gap-6 py-4 lg:grid-cols-12 lg:gap-10 lg:py-6">
        {{-- Gallery --}}
        <div class="lg:col-span-5">
            <div class="card overflow-hidden p-2 lg:sticky lg:top-[7.5rem]">
                <div x-ref="stage" class="relative aspect-square overflow-hidden rounded-lg bg-paper">
                    <template x-for="(s, i) in slides" :key="i">
                        <div x-show="image === i" class="absolute inset-0">
                            <template x-if="s.type === 'image'"><img :src="s.src" :alt="p.name + ' — image ' + (i + 1)" class="img-contain cursor-zoom-in" @click="lightbox = true"></template>
                            <template x-if="s.type === 'video' && !isYouTube(s.src)"><video :src="s.src" controls playsinline class="h-full w-full object-cover"></video></template>
                            <template x-if="s.type === 'video' && isYouTube(s.src)"><iframe :src="embed(s.src)" class="h-full w-full" allow="autoplay; encrypted-media" allowfullscreen title="Product video"></iframe></template>
                        </div>
                    </template>
                    <span class="absolute left-3 top-3 flex flex-col gap-1">
                        @if($product->discount_percent > 0)<span class="badge badge-off">{{ $product->discount_percent }}% OFF</span>@endif
                        @if($product->is_new)<span class="badge badge-new">NEW</span>@endif
                    </span>
                    <button type="button" @click="$store.wishlist.toggle(p.id)" class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-slate shadow-sm hover:text-berry" :class="$store.wishlist.has(p.id) && 'text-berry'" aria-label="Save for later"><x-ico name="heart" :size="18" ::class="$store.wishlist.has(p.id) && 'fill-current'" /></button>
                    <template x-if="slides.length > 1">
                        <div>
                            <button type="button" @click="prev()" class="rail-btn absolute left-2 top-1/2 -translate-y-1/2" aria-label="Previous image"><x-ico name="chevron-left" :size="18" /></button>
                            <button type="button" @click="next()" class="rail-btn absolute right-2 top-1/2 -translate-y-1/2" aria-label="Next image"><x-ico name="chevron-right" :size="18" /></button>
                        </div>
                    </template>
                </div>
                <div class="no-scrollbar mt-2 flex gap-2 overflow-x-auto" x-show="slides.length > 1">
                    <template x-for="(s, i) in slides" :key="'t' + i">
                        <button type="button" @click="image = i" class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 bg-paper" :class="image === i ? 'border-leaf' : 'border-transparent'" :aria-label="`Image ${i + 1}`">
                            <template x-if="s.type === 'image'"><img :src="s.src" alt="" class="img-cover"></template>
                            <template x-if="s.type === 'video'"><span class="grid h-full w-full place-items-center bg-ink text-white"><x-ico name="play" :size="16" /></span></template>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Buy panel --}}
        <div class="lg:col-span-7">
            <div class="flex items-center gap-2 text-xs font-semibold text-slate">
                @if($product->is_veg !== null)<span class="veg-mark {{ $product->is_veg ? '' : 'nonveg' }}"></span> <span>{{ $product->is_veg ? 'Vegetarian' : 'Non-vegetarian' }}</span> <span class="text-mist">·</span>@endif
                @if($product->brand)<a href="{{ route('shop.index', ['brand' => $product->brand]) }}" class="text-leaf hover:underline">{{ $product->brand }}</a>@endif
            </div>
            <h1 class="mt-1.5 text-xl font-extrabold leading-tight lg:text-3xl">{{ $product->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                @if($product->review_count > 0)<a href="#reviews" class="inline-flex items-center gap-1.5"><x-rating :value="$product->rating" :size="14" /> <span class="text-sm font-semibold">{{ number_format($product->rating, 1) }}</span><span class="text-xs text-slate">({{ number_format($product->review_count) }} ratings)</span></a>@else<span class="text-xs text-slate">No reviews yet</span>@endif
                <button type="button" @click="share()" class="inline-flex items-center gap-1 text-xs font-semibold text-slate hover:text-leaf"><x-ico name="share" :size="14" /> Share</button>
            </div>

            {{-- Pack sizes --}}
            @if(count($product->sizes ?? []) > 1)
                <div class="mt-5">
                    <p class="text-xs font-extrabold uppercase tracking-wider text-slate">Select pack size</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($product->sizes as $s)
                            <button type="button" @click="size = @js($s)" class="chip" :class="size === @js($s) && 'chip-active'">{{ $s }}</button>
                        @endforeach
                    </div>
                </div>
            @elseif($product->unit)
                <p class="mt-4 inline-flex items-center gap-2 rounded-lg bg-paper px-3 py-1.5 text-sm font-semibold">{{ $product->unit }}</p>
            @endif

            {{-- Price + add --}}
            <div class="card mt-5 p-4 sm:p-5" x-ref="buy">
                <div class="flex flex-wrap items-end gap-x-3 gap-y-1">
                    <p class="text-2xl font-extrabold tabular lg:text-3xl">{{ money($product->price) }}</p>
                    @if($product->compare_at_price)
                        <p class="text-base text-mist tabular strike">MRP {{ money($product->compare_at_price) }}</p>
                        <span class="badge badge-soft">You save {{ money($product->compare_at_price - $product->price) }}</span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate">Inclusive of all taxes</p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    @if($product->in_stock)
                        <button type="button" x-show="!qty" @click="add()" class="btn btn-primary btn-lg min-w-44">Add to cart</button>
                        <div x-show="qty" x-cloak class="stepper stepper-lg">
                            <button type="button" @click="dec()" aria-label="Decrease quantity"><x-ico name="minus" :size="18" /></button>
                            <span x-text="qty"></span>
                            <button type="button" @click="add()" aria-label="Increase quantity"><x-ico name="plus" :size="18" /></button>
                        </div>
                        <button type="button" @click="buyNow()" class="btn btn-accent btn-lg">Buy now</button>
                        @if($product->max_qty)<p class="w-full text-xs text-slate">Max {{ $product->max_qty }} per order</p>@endif
                    @else
                        <span class="badge badge-warn">Currently out of stock</span>
                        <button type="button" @click="$store.wishlist.toggle(p.id); $store.ui.notify('Saved — we’ll keep it in your list')" class="btn btn-outline">Notify me</button>
                    @endif
                </div>

                <ul class="mt-5 grid gap-2 border-t border-line pt-4 text-xs text-slate sm:grid-cols-3">
                    <li class="flex items-center gap-2"><x-ico name="bolt" :size="16" class="text-leaf" /> {{ $gs['delivery_promise'] ?? 'Fast delivery' }}</li>
                    <li class="flex items-center gap-2"><x-ico name="truck" :size="16" class="text-leaf" /> @if($threshold > 0)Free delivery over {{ money($threshold) }}@else Doorstep delivery @endif</li>
                    <li class="flex items-center gap-2"><x-ico name="rotate" :size="16" class="text-leaf" /> Easy returns & refunds</li>
                </ul>
            </div>

            {{-- Details tabs --}}
            <div class="card mt-5 overflow-hidden">
                <div class="no-scrollbar flex gap-1 overflow-x-auto border-b border-line px-2">
                    @foreach(['details' => 'Product details', 'ingredients' => 'Ingredients', 'storage' => 'Storage & shelf life', 'more' => 'Delivery & returns'] as $k => $label)
                        @if($k === 'ingredients' && !$product->ingredients) @continue @endif
                        <button type="button" @click="tab = @js($k)" class="shrink-0 border-b-2 px-3 py-3 text-sm font-bold transition-colors" :class="tab === @js($k) ? 'border-leaf text-leaf' : 'border-transparent text-slate hover:text-ink'">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="p-4 sm:p-5">
                    <div x-show="tab === 'details'" class="prose-g text-sm">
                        {!! $product->details ?: '<p>'.e($product->description).'</p>' !!}
                        @if($facts)
                            <dl class="!mt-4 grid gap-2 text-sm sm:grid-cols-2">
                                @foreach($facts as $k => $v)<div class="flex gap-2 rounded-lg bg-paper px-3 py-2"><dt class="w-32 shrink-0 text-slate">{{ $k }}</dt><dd class="font-semibold text-ink">{{ $v }}</dd></div>@endforeach
                            </dl>
                        @endif
                    </div>
                    @if($product->ingredients)<div x-show="tab === 'ingredients'" x-cloak class="prose-g text-sm"><p>{{ $product->ingredients }}</p></div>@endif
                    <div x-show="tab === 'storage'" x-cloak class="prose-g text-sm">
                        @if($product->storage_instructions)<p>{{ $product->storage_instructions }}</p>@endif
                        @if($product->shelf_life)<p><strong>Shelf life:</strong> {{ $product->shelf_life }}. Shelf life is counted from the date of manufacture; we always dispatch the freshest available stock.</p>@endif
                        @if(!$product->storage_instructions && !$product->shelf_life)<p>Store in a cool, dry place away from direct sunlight unless stated otherwise on the pack.</p>@endif
                    </div>
                    <div x-show="tab === 'more'" x-cloak class="prose-g text-sm">
                        <p><strong>Delivery:</strong> {{ $gs['delivery_promise'] ?? 'Fast delivery' }} in serviceable areas. Delivery slots and charges are shown at checkout@if($threshold > 0); orders over {{ money($threshold) }} ship free@endif.</p>
                        <p><strong>Returns:</strong> Not happy with the freshness or quality? Report it from your orders within 24 hours of delivery for a replacement or refund. <a href="/returns">Read the policy</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Frequently bought together --}}
    @if($completeTheLook->isNotEmpty())
        <section class="g-container section">
            <x-section-head title="Frequently bought together" />
            <x-rail :products="$completeTheLook" />
        </section>
    @endif

    {{-- Reviews --}}
    <section id="reviews" class="g-container section">
        <div class="card grid gap-8 p-5 sm:p-6 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <h2 class="section-title">Ratings & reviews</h2>
                <div class="mt-3 flex items-end gap-3">
                    <p class="text-5xl font-extrabold leading-none">{{ $product->review_count ? number_format($product->rating, 1) : 'New' }}</p>
                    <div><x-rating :value="$product->rating" :size="16" /><p class="mt-1 text-xs text-slate">{{ number_format($product->review_count) }} {{ Str::plural('rating', $product->review_count) }}</p></div>
                </div>
                <ul class="mt-4 space-y-1.5">
                    @for($i = 5; $i >= 1; $i--)
                        @php $n = $breakdown[$i] ?? 0; $pct = $product->review_count ? round($n / max(1, $breakdown->sum()) * 100) : 0; @endphp
                        <li class="flex items-center gap-2 text-xs"><span class="w-4 font-semibold">{{ $i }}★</span><span class="h-2 flex-1 overflow-hidden rounded-full bg-paper"><span class="block h-full rounded-full bg-star" style="width: {{ $pct }}%"></span></span><span class="w-6 text-right text-mist">{{ $n }}</span></li>
                    @endfor
                </ul>
                <div x-data="reviewForm()" class="mt-5">
                    <button type="button" @click="open = !open" class="btn btn-outline btn-block">Write a review</button>
                    @if(session('review_status'))<p class="mt-3 rounded-lg bg-leaf-light p-3 text-sm text-leaf-dark">{{ session('review_status') }}</p>@endif
                    <form x-show="open" x-collapse x-cloak method="post" action="{{ route('products.reviews.store', $product->slug) }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <span class="label">Your rating</span>
                            <div class="flex gap-1">
                                @for($i = 1; $i <= 5; $i++)<button type="button" @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" class="text-star" aria-label="{{ $i }} stars"><svg width="26" height="26" viewBox="0 0 24 24" :fill="(hover || rating) >= {{ $i }} ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.6"><path d="m12 3 2.8 5.8 6.4.9-4.6 4.5 1.1 6.3L12 17.5l-5.7 3 1.1-6.3L2.8 9.7l6.4-.9L12 3z"/></svg></button>@endfor
                            </div>
                            <input type="hidden" name="rating" :value="rating">
                        </div>
                        <label class="block"><span class="label">Name</span><input name="name" required value="{{ old('name', auth()->user()?->name) }}" class="field"></label>
                        <label class="block"><span class="label">Email (not published)</span><input name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" class="field"></label>
                        <label class="block"><span class="label">Title</span><input name="title" value="{{ old('title') }}" class="field"></label>
                        <label class="block"><span class="label">Review</span><textarea name="body" required rows="4" class="field">{{ old('body') }}</textarea></label>
                        @if($errors->any())<p class="error-text">{{ $errors->first() }}</p>@endif
                        <button type="submit" class="btn btn-primary">Submit review</button>
                    </form>
                </div>
            </div>
            <div class="lg:col-span-8">
                @if($reviews->isEmpty())
                    <p class="text-sm text-slate">Be the first to review this product.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach($reviews as $r)
                            <li class="py-4 first:pt-0">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-full bg-leaf-light text-xs font-extrabold text-leaf-dark">{{ Str::upper(Str::substr($r->name, 0, 1)) }}</span><span class="text-sm font-bold">{{ $r->name }}</span><span class="badge badge-soft">Verified</span></div>
                                    <span class="text-xs text-mist">{{ $r->created_at->format('d M Y') }}</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2"><x-rating :value="$r->rating" :size="13" />@if($r->title)<span class="text-sm font-semibold">{{ $r->title }}</span>@endif</div>
                                <p class="mt-1.5 text-sm leading-relaxed text-slate">{{ $r->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    {{-- Similar products --}}
    @if($related->isNotEmpty())
        <section class="g-container section">
            <x-section-head title="Similar products" :href="$product->category?->url" :label="'More in '.($product->category?->name ?? 'category')" />
            <x-rail :products="$related" />
        </section>
    @endif

    <div x-data="productRail('recent')" data-exclude="{{ $product->slug }}" x-show="items.length" x-cloak class="g-container section">
        <x-section-head title="Recently viewed" />
        <div x-data="rail()" class="relative"><div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0"><template x-for="p in items" :key="p.id"><div class="w-40 shrink-0 sm:w-44" data-slide>@include('partials.card-dynamic')</div></template></div></div>
    </div>

    {{-- Sticky mobile bar --}}
    <div x-show="showSticky" x-cloak x-transition class="fixed inset-x-0 bottom-16 z-40 border-t border-line bg-white p-3 shadow-float lg:hidden">
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">{{ $product->name }}</p><p class="text-sm font-extrabold tabular">{{ money($product->price) }} <span x-text="size" class="text-xs font-normal text-slate"></span></p></div>
            @if($product->in_stock)
                <button type="button" x-show="!qty" @click="add()" class="btn btn-primary">Add to cart</button>
                <div x-show="qty" x-cloak class="stepper"><button type="button" @click="dec()" aria-label="Decrease"><x-ico name="minus" :size="14" /></button><span x-text="qty"></span><button type="button" @click="add()" aria-label="Increase"><x-ico name="plus" :size="14" /></button></div>
            @endif
        </div>
    </div>

    {{-- Lightbox --}}
    <div x-show="lightbox" x-cloak x-transition.opacity class="fixed inset-0 z-[95] flex items-center justify-center bg-ink/90 p-4" @click.self="lightbox = false" @keydown.escape.window="lightbox = false">
        <button type="button" @click="lightbox = false" class="absolute right-4 top-4 grid h-11 w-11 place-items-center text-white" aria-label="Close"><x-ico name="close" :size="26" /></button>
        <template x-if="slides[image]?.type === 'image'"><img :src="slides[image].src" alt="" class="max-h-full max-w-full rounded-xl object-contain"></template>
        <button type="button" @click="prev()" class="absolute left-4 top-1/2 grid h-12 w-12 -translate-y-1/2 place-items-center text-white" aria-label="Previous"><x-ico name="chevron-left" :size="28" /></button>
        <button type="button" @click="next()" class="absolute right-4 top-1/2 grid h-12 w-12 -translate-y-1/2 place-items-center text-white" aria-label="Next"><x-ico name="chevron-right" :size="28" /></button>
    </div>
</div>
@endsection
