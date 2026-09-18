@extends('layouts.app')

@section('content')
@php
    $card = $product->toCard();
    $hs = tsetting('site');
    $g = tsetting('home');
    $crumbs = [];
    if ($product->category?->parent) { $crumbs[$product->category->parent->name] = $product->category->parent->url; }
    if ($product->category) { $crumbs[$product->category->name] = $product->category->url; }
    $crumbs[$product->name] = null;
    $threshold = (int) setting('site.free_shipping_threshold', 0);
    $benefits = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $product->benefits))));
    $nutrition = array_filter($product->nutrition ?? [], fn ($r) => ! empty($r['label']));
    $certs = array_values(array_filter($g['certifications'] ?? [], fn ($c) => ! empty($c['label'])));
    $stats = array_values(array_filter($g['story_stats'] ?? [], fn ($s) => ! empty($s['value'])));
    $values = array_values(array_filter($g['values_strip'] ?? [], fn ($v) => ! empty($v['label'])));
    $tabs = array_filter([
        'usage' => $product->usage_instructions ? 'How to Use' : null,
        'description' => 'Description',
        'ingredients' => $product->ingredients ? 'Ingredients' : null,
        'nutrition' => $nutrition ? 'Nutrition' : null,
        'shipping' => 'Shipping & Returns',
    ]);
    $firstTab = array_key_first($tabs);
    $benefitIcons = ['leaf', 'badge', 'star', 'shield'];
@endphp
<div x-data="productPage(@js($card))" x-init="tab = @js($firstTab)">
    <div class="h-container hidden pt-5 lg:block"><x-breadcrumbs :items="$crumbs" /></div>

    <section class="h-container grid gap-8 py-6 lg:grid-cols-12 lg:gap-12">
        {{-- Gallery: big beige box + thumbnails below --}}
        <div class="min-w-0 lg:col-span-6">
            <div x-ref="stage" class="relative aspect-square min-w-0 overflow-hidden rounded-2xl border border-line-soft bg-cream-dark">
                <template x-for="(s, i) in slides" :key="i">
                    <div x-show="image === i" class="absolute inset-0">
                        <template x-if="s.type === 'image'"><div class="relative h-full w-full cursor-zoom-in" @mouseenter="zoom.on = true" @mouseleave="zoom.on = false" @mousemove="move($event)" @click="lightbox = true"><img :src="s.src" :alt="p.name" class="img-cover transition-transform duration-300" :style="zoom.on ? `transform: scale(1.6); transform-origin: ${zoom.x}% ${zoom.y}%` : ''"></div></template>
                        <template x-if="s.type === 'video' && !isYouTube(s.src)"><video :src="s.src" controls playsinline class="h-full w-full object-cover"></video></template>
                        <template x-if="s.type === 'video' && isYouTube(s.src)"><iframe :src="embed(s.src)" class="h-full w-full" allow="autoplay; encrypted-media" allowfullscreen title="Product video"></iframe></template>
                    </div>
                </template>
                @if($product->is_veg !== null)<span class="absolute right-4 top-4 grid h-6 w-6 place-items-center rounded bg-white"><span class="veg-mark {{ $product->is_veg ? '' : 'nonveg' }}"></span></span>@endif
                @if($product->discount_percent > 0)<span class="pcard-off">{{ $product->discount_percent }}% OFF</span>@endif
                <template x-if="slides.length > 1"><div><button type="button" @click="prev()" class="rail-btn absolute left-3 top-1/2 -translate-y-1/2" aria-label="Previous"><x-ico name="chevron-left" :size="18" /></button><button type="button" @click="next()" class="rail-btn absolute right-3 top-1/2 -translate-y-1/2" aria-label="Next"><x-ico name="chevron-right" :size="18" /></button></div></template>
            </div>
            <div class="no-scrollbar mt-3 flex min-w-0 gap-3 overflow-x-auto" x-show="slides.length > 1">
                <template x-for="(s, i) in slides" :key="'t' + i">
                    <button type="button" @click="image = i" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border-2 bg-cream-dark" :class="image === i ? 'border-ink' : 'border-transparent'" :aria-label="`Image ${i + 1}`">
                        <template x-if="s.type === 'image'"><img :src="s.src" alt="" class="img-cover"></template>
                        <template x-if="s.type === 'video'"><span class="grid h-full w-full place-items-center bg-maroon text-cream"><x-ico name="play" :size="16" /></span></template>
                    </button>
                </template>
            </div>
        </div>

        {{-- Details --}}
        <div class="min-w-0 lg:col-span-6">
            <h1 class="display text-2xl text-red lg:text-[2rem]">{{ $product->name }}</h1>
            <p class="mt-2 text-sm text-ink">{{ $product->category?->name }}@if($product->brand) · {{ $product->brand }}@endif</p>
            @if($product->description)<p class="mt-3 text-sm leading-relaxed text-ink/85">{{ $product->description }}</p>@endif

            @if($benefits)
                <p class="mt-5 text-sm font-semibold">Key Benefits</p>
                <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach(array_slice($benefits, 0, 4) as $i => $b)<li class="flex items-center gap-3 text-sm"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-cream-dark text-red"><x-ico :name="$benefitIcons[$i % 4]" :size="20" :stroke="1.5" /></span><span class="leading-snug">{{ $b }}</span></li>@endforeach
                </ul>
            @endif

            @if(!empty($g['offer_badge']))<p class="mt-5 inline-block rounded-md bg-red px-3 py-1.5 text-xs font-semibold text-cream">{{ $g['offer_badge'] }} <span class="font-normal text-cream/80">on selected products</span></p>@endif

            {{-- Pack size cards --}}
            <div class="mt-5" x-ref="buy">
                <p class="text-xs font-medium text-muted">Select Pack Size</p>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach($product->sizes ?? [] as $s)
                        <button type="button" @click="size = @js($s)" class="min-w-[9rem] overflow-hidden rounded-xl border-2 text-left transition-colors" :class="size === @js($s) ? 'border-red' : 'border-line hover:border-gold'">
                            <span class="block px-3 py-1.5 text-center text-sm font-semibold" :class="size === @js($s) ? 'bg-red text-cream' : 'bg-cream-dark text-ink'">{{ $s }}</span>
                            <span class="block px-3 py-2"><span class="flex items-baseline gap-2"><span class="font-semibold tabular">{{ money($product->price) }}</span>@if($product->compare_at_price)<s class="text-xs text-muted">{{ money($product->compare_at_price) }}</s>@endif</span>@if($product->compare_at_price)<span class="mt-1 inline-block rounded-full bg-ink px-2 py-0.5 text-[0.625rem] font-semibold text-cream">Save {{ money($product->compare_at_price - $product->price) }}/-</span>@endif</span>
                        </button>
                    @endforeach
                </div>
                @if(!empty($product->dietary_tags))<ul class="mt-4 flex flex-wrap gap-1.5">@foreach($product->dietary_tags as $t)<li class="badge badge-outline">{{ $t }}</li>@endforeach</ul>@endif
                <div class="mt-5 flex items-center gap-3">
                    @if($product->in_stock)
                        <div class="stepper stepper-lg"><button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity"><x-ico name="minus" :size="16" /></button><span x-text="qty"></span><button type="button" @click="qty = Math.min(max, qty + 1)" aria-label="Increase quantity"><x-ico name="plus" :size="16" /></button></div>
                        <button type="button" @click="add()" class="btn btn-gold btn-lg flex-1 rounded-full">Add to Cart</button>
                        <button type="button" @click="$store.wishlist.toggle(p.id)" class="grid h-[3.125rem] w-[3.125rem] shrink-0 place-items-center rounded-full border border-line bg-white text-muted hover:border-gold hover:text-red" :class="$store.wishlist.has(p.id) && '!text-red'" aria-label="Add to wishlist"><x-ico name="heart" :size="18" ::class="$store.wishlist.has(p.id) && 'fill-current'" /></button>
                    @else
                        <button type="button" class="btn btn-lg flex-1 rounded-full bg-line text-muted" disabled>Sold out</button>
                        <button type="button" @click="$store.wishlist.toggle(p.id)" class="btn btn-outline btn-lg rounded-full">Notify me</button>
                    @endif
                </div>
                @if($product->in_stock)<button type="button" @click="add(true)" class="mt-3 w-full text-center text-sm font-semibold text-red underline-offset-4 hover:underline">Buy it now</button>@endif
                <p x-show="inCart" x-cloak class="mt-2 text-center text-xs font-medium text-leaf"><span x-text="inCart"></span> already in your cart · <a href="{{ route('cart') }}" class="underline">view cart</a></p>
                <ul class="mt-5 grid gap-2 border-t border-line pt-4 text-xs text-muted sm:grid-cols-3">
                    <li class="flex items-center gap-2"><x-ico name="truck" :size="16" class="text-gold" /> {{ $hs['delivery_note'] ?? 'Pan-India delivery' }}</li>
                    <li class="flex items-center gap-2"><x-ico name="badge" :size="16" class="text-gold" /> Lab-tested, traceable batch</li>
                    <li class="flex items-center gap-2"><x-ico name="rotate" :size="16" class="text-gold" /> 7-day quality promise</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Pill tabs + content panel --}}
    <section class="h-container pb-8">
        <div class="mx-auto flex max-w-4xl flex-wrap justify-center gap-3">
            @foreach($tabs as $k => $label)<button type="button" @click="tab = @js($k)" class="rounded-full border px-6 py-2.5 text-sm font-semibold transition-colors" :class="tab === @js($k) ? 'border-red bg-red text-cream' : 'border-line bg-white text-ink hover:border-gold'">{{ $label }}</button>@endforeach
        </div>
        <div class="banner-round relative mt-5 bg-maroon-deep p-6 text-cream sm:p-10">
            @if($product->usage_instructions)
                <div x-show="tab === 'usage'" x-cloak>
                    <h2 class="text-center font-serif text-2xl font-semibold"><span class="text-gold-light">How to use</span> — {{ $product->name }}</h2>
                    <p class="mx-auto mt-4 max-w-3xl text-center text-sm leading-relaxed text-cream/90">{{ $product->usage_instructions }}</p>
                    @if($product->storage_instructions)<p class="mx-auto mt-3 max-w-3xl text-center text-xs text-cream/70"><strong class="text-gold-light">Storage:</strong> {{ $product->storage_instructions }}</p>@endif
                </div>
            @endif
            <div x-show="tab === 'description'" x-cloak>
                <h2 class="text-center font-serif text-2xl font-semibold text-gold-light">Description</h2>
                <div class="prose-h mx-auto mt-4 max-w-3xl [&_*]:!text-cream/90 [&_h2]:!text-gold-light [&_h3]:!text-gold-light">{!! $product->details ?: '<p>'.e($product->description).'</p>' !!}</div>
                <dl class="mx-auto mt-6 grid max-w-3xl gap-3 sm:grid-cols-3">
                    @foreach(array_filter(['Origin' => $product->country_of_origin, 'Shelf life' => $product->shelf_life, 'SKU' => $product->sku]) as $k => $v)<div class="rounded-lg bg-maroon px-4 py-3 text-center"><dt class="text-[0.625rem] uppercase tracking-wider text-gold-light">{{ $k }}</dt><dd class="mt-1 text-sm font-semibold">{{ $v }}</dd></div>@endforeach
                </dl>
            </div>
            @if($product->ingredients)<div x-show="tab === 'ingredients'" x-cloak><h2 class="text-center font-serif text-2xl font-semibold text-gold-light">Ingredients</h2><p class="mx-auto mt-4 max-w-3xl text-center text-sm leading-relaxed text-cream/90">{{ $product->ingredients }}</p></div>@endif
            @if($nutrition)
                <div x-show="tab === 'nutrition'" x-cloak>
                    <h2 class="text-center font-serif text-2xl font-semibold text-gold-light">Nutrition</h2>
                    <p class="mt-1 text-center text-xs text-cream/70">Approximate values per 100 g / 100 ml</p>
                    <div class="mx-auto mt-5 grid max-w-3xl grid-cols-2 gap-3 sm:grid-cols-4">@foreach($nutrition as $row)<div class="rounded-lg bg-maroon px-3 py-3 text-center"><p class="text-lg font-semibold text-gold-light tabular">{{ $row['value'] ?? '' }}</p><p class="mt-0.5 text-xs text-cream/80">{{ $row['label'] }}</p></div>@endforeach</div>
                </div>
            @endif
            <div x-show="tab === 'shipping'" x-cloak class="mx-auto max-w-3xl text-center text-sm leading-relaxed text-cream/90">
                <h2 class="font-serif text-2xl font-semibold text-gold-light">Shipping & Returns</h2>
                <p class="mt-4">{{ $hs['delivery_note'] ?? 'Pan-India delivery' }}. Orders placed before 2 pm ship the same working day @if($threshold > 0), with free delivery on orders above {{ money($threshold) }} @endif.</p>
                <p class="mt-2">Damaged, leaking or not as described? Tell us within 7 days for a replacement or full refund. <a href="/returns" class="text-gold-light underline">Read the policy</a>.</p>
            </div>
        </div>
    </section>

    {{-- Certifications --}}
    @if($certs)
        <section class="h-container pb-8"><h2 class="title-c">{{ $g['certifications_heading'] ?? 'Our Certifications' }}</h2><div class="no-scrollbar mt-5 flex gap-3 overflow-x-auto pb-2 lg:justify-center">@foreach($certs as $c)@php $logo = \App\Support\Media::url($c['logo'] ?? null); @endphp<div class="cert-badge shrink-0">@if($logo)<img src="{{ $logo }}" alt="{{ $c['label'] }}" class="h-10 w-auto object-contain" loading="lazy">@else<span class="flex items-center gap-2"><x-ico name="badge" :size="18" class="text-gold" />{{ $c['label'] }}</span>@endif</div>@endforeach</div></section>
    @endif

    {{-- Stats strip --}}
    @if($stats)
        <section class="bg-red py-10 text-cream">
            <div class="h-container">
                <h2 class="text-center font-serif text-2xl font-semibold text-gold-light sm:text-3xl">{{ $g['story_heading'] ?? 'Our promise' }}</h2>
                <div class="mt-8 grid grid-cols-2 gap-6 text-center sm:grid-cols-4 lg:divide-x lg:divide-cream/20">
                    @foreach(array_slice($stats, 0, 5) as $s)<div><p class="font-serif text-3xl font-semibold">{{ $s['value'] }}</p><p class="mt-1 text-sm text-cream/85">{{ $s['label'] ?? '' }}</p></div>@endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Values strip --}}
    @if($values)
        <section class="h-container pt-4"><div class="rounded-2xl bg-cream-dark px-4 pb-6 pt-10 lg:px-10"><div class="-mt-16 grid grid-cols-3 gap-4 sm:grid-cols-6">@foreach(array_slice($values, 0, 6) as $v)<div class="flex flex-col items-center text-center"><span class="icon-round"><x-ico :name="$v['icon'] ?? 'check'" :size="26" :stroke="1.4" /></span><p class="mt-3 text-xs font-medium sm:text-sm">{{ $v['label'] }}</p></div>@endforeach</div></div></section>
    @endif

    {{-- Related products band --}}
    @if($related->isNotEmpty() || $completeTheLook->isNotEmpty())
        <section class="mt-10 bg-cream-dark/70 py-10">
            <div class="h-container">
                <h2 class="title-c !text-ink">Related Products</h2>
                <div class="mt-6"><x-rail :products="$related->isNotEmpty() ? $related : $completeTheLook" /></div>
            </div>
        </section>
    @endif

    {{-- Farmers / story block --}}
    @if(!empty($g['story_heading']))
        @php $paragraphs = preg_split('/\n\s*\n/', trim((string) ($g['story_text'] ?? ''))); @endphp
        <section class="h-container py-10">
            <div class="grid items-center gap-8 lg:grid-cols-12">
                <div class="lg:col-span-5"><div class="rounded-2xl bg-cream-dark p-4"><div class="banner-round aspect-[4/5]">@if(!empty($g['story_image']))<img src="{{ \App\Support\Media::url($g['story_image']) }}" alt="" class="h-full w-full object-cover" loading="lazy">@endif</div></div></div>
                <div class="lg:col-span-7">
                    <h2 class="font-serif text-2xl font-semibold text-red lg:text-3xl">{{ $g['story_heading'] }}</h2>
                    <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink/85">@foreach($paragraphs as $para)<p>{{ $para }}</p>@endforeach</div>
                    @if(!empty($g['story_cta_label']))<a href="{{ $g['story_cta_url'] ?? '/about' }}" class="btn btn-primary mt-6 rounded-full px-7">{{ $g['story_cta_label'] }}</a>@endif
                </div>
            </div>
        </section>
    @endif

    {{-- Reviews --}}
    <section id="reviews" class="h-container pb-10">
        <div class="card grid gap-8 p-6 lg:grid-cols-12 lg:p-8">
            <div class="lg:col-span-4">
                <h2 class="font-serif text-2xl font-semibold text-red">Customer Reviews</h2>
                <div class="mt-4 flex items-end gap-3"><p class="font-serif text-5xl font-semibold leading-none">{{ $product->review_count ? number_format($product->rating, 1) : 'New'}}</p><div><x-rating :value="$product->rating" :size="16" /><p class="mt-1 text-xs text-muted">{{ number_format($product->review_count) }} {{ Str::plural('review', $product->review_count) }}</p></div></div>
                <ul class="mt-4 space-y-1.5">@for($i = 5; $i >= 1; $i--)@php $n = $breakdown[$i] ?? 0; $pct = $breakdown->sum() ? round($n / $breakdown->sum() * 100) : 0; @endphp<li class="flex items-center gap-2 text-xs"><span class="w-5 font-semibold">{{ $i }}★</span><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-cream-dark"><span class="block h-full rounded-full bg-gold" style="width: {{ $pct }}%"></span></span><span class="w-6 text-right text-muted">{{ $n }}</span></li>@endfor</ul>
                <div x-data="reviewForm()" class="mt-5">
                    <button type="button" @click="open = !open" class="btn btn-outline btn-block rounded-full">Write a review</button>
                    @if(session('review_status'))<p class="mt-3 rounded-lg bg-cream p-3 text-sm text-maroon">{{ session('review_status') }}</p>@endif
                    <form x-show="open" x-collapse x-cloak method="post" action="{{ route('products.reviews.store', $product->slug) }}" class="mt-4 space-y-3">
                        @csrf
                        <div><span class="label">Your rating</span><div class="flex gap-1">@for($i = 1; $i <= 5; $i++)<button type="button" @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" class="text-star" aria-label="{{ $i }} stars"><svg width="26" height="26" viewBox="0 0 24 24" :fill="(hover || rating) >= {{ $i }} ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="m12 3 2.8 5.8 6.4.9-4.6 4.5 1.1 6.3L12 17.5l-5.7 3 1.1-6.3L2.8 9.7l6.4-.9L12 3z"/></svg></button>@endfor</div><input type="hidden" name="rating" :value="rating"></div>
                        <label class="block"><span class="label">Name</span><input name="name" required value="{{ old('name', auth()->user()?->name) }}" class="field"></label>
                        <label class="block"><span class="label">Email (not published)</span><input name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" class="field"></label>
                        <label class="block"><span class="label">Title</span><input name="title" value="{{ old('title') }}" class="field"></label>
                        <label class="block"><span class="label">Review</span><textarea name="body" required rows="4" class="field">{{ old('body') }}</textarea></label>
                        @if($errors->any())<p class="error-text">{{ $errors->first() }}</p>@endif
                        <button type="submit" class="btn btn-primary rounded-full">Submit review</button>
                    </form>
                </div>
            </div>
            <div class="lg:col-span-8">
                @if($reviews->isEmpty())<p class="text-sm text-muted">No reviews yet — be the first to share how you cooked with it.</p>@else
                    <ul class="divide-y divide-line-soft">
                        @foreach($reviews as $r)
                            <li class="py-4 first:pt-0"><div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-full bg-cream-dark font-serif text-sm font-semibold text-red">{{ Str::upper(Str::substr($r->name, 0, 1)) }}</span><span class="text-sm font-semibold">{{ $r->name }}</span><span class="badge badge-soft">Verified</span></div><span class="text-xs text-muted">{{ $r->created_at->format('d M Y') }}</span></div><div class="mt-2 flex items-center gap-2"><x-rating :value="$r->rating" :size="13" />@if($r->title)<span class="text-sm font-semibold">{{ $r->title }}</span>@endif</div><p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $r->body }}</p></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    <div x-data="productRail('recent')" data-exclude="{{ $product->slug }}" x-show="items.length" x-cloak class="h-container pb-10">
        <h2 class="title-c !text-ink">Recently Viewed</h2>
        <div x-data="rail()" class="relative mt-6"><div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0"><template x-for="p in items" :key="p.id"><div class="w-[11.5rem] shrink-0 sm:w-[13.5rem] lg:w-[15.25rem]" data-slide>@include('partials.card-dynamic')</div></template></div></div>
    </div>

    {{-- Sticky mobile add-to-cart --}}
    <div x-show="showSticky" x-cloak x-transition class="above-tabs border-t border-line bg-warm p-3 shadow-float lg:hidden">
        <div class="flex items-center gap-3"><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $product->name }}</p><p class="font-serif text-base font-semibold tabular">{{ money($product->price) }} <span x-text="size" class="font-sans text-xs font-normal text-muted"></span></p></div>@if($product->in_stock)<button type="button" @click="add()" class="btn btn-gold rounded-full">Add to Cart</button>@endif</div>
    </div>

    {{-- Lightbox --}}
    <div x-show="lightbox" x-cloak x-transition.opacity class="fixed inset-0 z-[95] flex items-center justify-center bg-maroon-deep/95 p-4" @click.self="lightbox = false" @keydown.escape.window="lightbox = false">
        <button type="button" @click="lightbox = false" class="absolute right-4 top-4 grid h-11 w-11 place-items-center text-cream" aria-label="Close"><x-ico name="close" :size="26" /></button>
        <template x-if="slides[image]?.type === 'image'"><img :src="slides[image].src" alt="" class="max-h-full max-w-full rounded-xl object-contain"></template>
        <button type="button" @click="prev()" class="absolute left-4 top-1/2 grid h-12 w-12 -translate-y-1/2 place-items-center text-cream" aria-label="Previous"><x-ico name="chevron-left" :size="28" /></button>
        <button type="button" @click="next()" class="absolute right-4 top-1/2 grid h-12 w-12 -translate-y-1/2 place-items-center text-cream" aria-label="Next"><x-ico name="chevron-right" :size="28" /></button>
    </div>
</div>
@endsection
