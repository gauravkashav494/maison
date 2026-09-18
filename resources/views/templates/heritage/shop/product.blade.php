@extends('layouts.app')

@section('content')
@php
    $card = $product->toCard();
    $hs = tsetting('site');
    $crumbs = [];
    if ($product->category?->parent) { $crumbs[$product->category->parent->name] = $product->category->parent->url; }
    if ($product->category) { $crumbs[$product->category->name] = $product->category->url; }
    $crumbs[$product->name] = null;
    $threshold = (int) setting('site.free_shipping_threshold', 0);
    $benefits = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $product->benefits))));
    $nutrition = array_filter($product->nutrition ?? [], fn ($r) => ! empty($r['label']));
    $facts = array_filter(['Brand' => $product->brand, 'Origin' => $product->country_of_origin, 'Shelf life' => $product->shelf_life, 'SKU' => $product->sku]);
    $tabs = array_filter([
        'description' => 'Description',
        'ingredients' => $product->ingredients ? 'Ingredients' : null,
        'nutrition' => $nutrition ? 'Nutrition' : null,
        'benefits' => $benefits ? 'Benefits' : null,
        'usage' => $product->usage_instructions ? 'How to use' : null,
        'shipping' => 'Shipping & returns',
    ]);
@endphp
<div x-data="productPage(@js($card))">
    <div class="h-container pt-5"><x-breadcrumbs :items="$crumbs" /></div>

    <section class="h-container grid gap-8 py-6 lg:grid-cols-12 lg:gap-12 lg:py-8">
        {{-- Gallery --}}
        <div class="min-w-0 lg:col-span-6">
            <div class="lg:sticky lg:top-[8.5rem]">
                <div class="grid min-w-0 gap-3 lg:grid-cols-[5rem_1fr]">
                    <div class="no-scrollbar order-2 flex min-w-0 gap-2 overflow-x-auto lg:order-1 lg:flex-col" x-show="slides.length > 1">
                        <template x-for="(s, i) in slides" :key="'t' + i">
                            <button type="button" @click="image = i" class="h-[4.5rem] w-[4.5rem] shrink-0 overflow-hidden rounded-lg border-2 bg-cream lg:w-full" :class="image === i ? 'border-gold' : 'border-transparent opacity-80 hover:opacity-100'" :aria-label="`Image ${i + 1}`">
                                <template x-if="s.type === 'image'"><img :src="s.src" alt="" class="img-cover"></template>
                                <template x-if="s.type === 'video'"><span class="grid h-full w-full place-items-center bg-maroon text-cream"><x-ico name="play" :size="16" /></span></template>
                            </button>
                        </template>
                    </div>
                    <div x-ref="stage" class="gold-frame order-1 relative aspect-square min-w-0 overflow-hidden rounded-xl bg-cream lg:order-2">
                        <template x-for="(s, i) in slides" :key="i">
                            <div x-show="image === i" class="absolute inset-0">
                                <template x-if="s.type === 'image'"><div class="relative h-full w-full cursor-zoom-in" @mouseenter="zoom.on = true" @mouseleave="zoom.on = false" @mousemove="move($event)" @click="lightbox = true"><img :src="s.src" :alt="p.name" class="img-cover transition-transform duration-300" :style="zoom.on ? `transform: scale(1.7); transform-origin: ${zoom.x}% ${zoom.y}%` : ''"></div></template>
                                <template x-if="s.type === 'video' && !isYouTube(s.src)"><video :src="s.src" controls playsinline class="h-full w-full object-cover"></video></template>
                                <template x-if="s.type === 'video' && isYouTube(s.src)"><iframe :src="embed(s.src)" class="h-full w-full" allow="autoplay; encrypted-media" allowfullscreen title="Product video"></iframe></template>
                            </div>
                        </template>
                        <span class="absolute left-4 top-4 flex flex-col gap-1">@if($product->discount_percent > 0)<span class="badge badge-off">{{ $product->discount_percent }}% off</span>@endif @if($product->is_new)<span class="badge badge-gold">New</span>@endif</span>
                        <button type="button" @click="lightbox = true" class="absolute bottom-4 right-4 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-ink shadow-sm" aria-label="Zoom"><x-ico name="zoom" :size="18" /></button>
                        <template x-if="slides.length > 1"><div><button type="button" @click="prev()" class="rail-btn absolute left-3 top-1/2 -translate-y-1/2 lg:hidden" aria-label="Previous"><x-ico name="chevron-left" :size="18" /></button><button type="button" @click="next()" class="rail-btn absolute right-3 top-1/2 -translate-y-1/2 lg:hidden" aria-label="Next"><x-ico name="chevron-right" :size="18" /></button></div></template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Buy panel --}}
        <div class="min-w-0 lg:col-span-6">
            <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                @if($product->is_veg !== null)<span class="veg-mark {{ $product->is_veg ? '' : 'nonveg' }}"></span>@endif
                @if($product->brand)<a href="{{ route('shop.index', ['brand' => $product->brand]) }}" class="eyebrow !text-[0.625rem] hover:text-red">{{ $product->brand }}</a>@endif
            </div>
            <h1 class="display mt-2 text-3xl text-maroon lg:text-4xl">{{ $product->name }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-4">
                @if($product->review_count > 0)<a href="#reviews"><x-rating :value="$product->rating" :count="$product->review_count" /></a>@else<span class="text-xs text-muted">Be the first to review</span>@endif
                <button type="button" @click="share()" class="inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-red"><x-ico name="share" :size="14" /> Share</button>
            </div>
            @if($product->description)<p class="mt-4 text-[0.9375rem] leading-relaxed text-muted">{{ $product->description }}</p>@endif
            @if(!empty($product->dietary_tags))<ul class="mt-4 flex flex-wrap gap-1.5">@foreach($product->dietary_tags as $t)<li class="badge badge-outline">{{ $t }}</li>@endforeach</ul>@endif

            @if(count($product->sizes ?? []) > 1)
                <div class="mt-6"><p class="label">Pack size</p><div class="flex flex-wrap gap-2">@foreach($product->sizes as $s)<button type="button" @click="size = @js($s)" class="chip" :class="size === @js($s) && 'chip-active'">{{ $s }}</button>@endforeach</div></div>
            @elseif($product->unit)
                <p class="mt-5 inline-flex items-center gap-2 rounded-md bg-cream px-3 py-1.5 text-sm font-medium"><x-ico name="package" :size="14" class="text-gold" /> {{ $product->unit }}</p>
            @endif

            <div class="card mt-6 p-5 sm:p-6" x-ref="buy">
                <div class="flex flex-wrap items-end gap-x-3 gap-y-1">
                    <p class="font-serif text-3xl font-semibold tabular lg:text-4xl">{{ money($product->price) }}</p>
                    @if($product->compare_at_price)<p class="text-base text-muted tabular strike">MRP {{ money($product->compare_at_price) }}</p><span class="badge badge-soft">Save {{ money($product->compare_at_price - $product->price) }} ({{ $product->discount_percent }}%)</span>@endif
                </div>
                <p class="mt-1 text-xs text-muted">Inclusive of all taxes</p>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    @if($product->in_stock)
                        <div class="stepper stepper-lg"><button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity"><x-ico name="minus" :size="16" /></button><span x-text="qty"></span><button type="button" @click="qty = Math.min(max, qty + 1)" aria-label="Increase quantity"><x-ico name="plus" :size="16" /></button></div>
                        <button type="button" @click="add()" class="btn btn-primary btn-lg flex-1 sm:flex-none sm:min-w-48">Add to cart</button>
                        <button type="button" @click="add(true)" class="btn btn-gold btn-lg">Buy now</button>
                        <button type="button" @click="$store.wishlist.toggle(p.id)" class="grid h-[3.125rem] w-[3.125rem] place-items-center rounded-lg border border-line text-muted hover:border-gold hover:text-red" :class="$store.wishlist.has(p.id) && '!text-red'" aria-label="Add to wishlist"><x-ico name="heart" :size="18" ::class="$store.wishlist.has(p.id) && 'fill-current'" /></button>
                    @else
                        <span class="badge badge-soft">Currently out of stock</span>
                        <button type="button" @click="$store.wishlist.toggle(p.id)" class="btn btn-outline">Notify me</button>
                    @endif
                </div>
                <p x-show="inCart" x-cloak class="mt-3 text-xs font-medium text-leaf"><span x-text="inCart"></span> already in your cart · <a href="{{ route('cart') }}" class="underline">view cart</a></p>
                @if($product->max_qty)<p class="mt-2 text-xs text-muted">Maximum {{ $product->max_qty }} per order.</p>@endif
                <ul class="mt-5 grid gap-2 border-t border-line-soft pt-4 text-xs text-muted sm:grid-cols-3">
                    <li class="flex items-center gap-2"><x-ico name="truck" :size="16" class="text-gold" /> {{ $hs['delivery_note'] ?? 'Pan-India delivery' }}</li>
                    <li class="flex items-center gap-2"><x-ico name="badge" :size="16" class="text-gold" /> Lab-tested, traceable batch</li>
                    <li class="flex items-center gap-2"><x-ico name="rotate" :size="16" class="text-gold" /> 7-day quality promise</li>
                </ul>
            </div>

            @if($facts)
                <dl class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach($facts as $k => $v)<div class="rounded-lg border border-line-soft bg-warm px-3 py-2"><dt class="text-[0.625rem] font-semibold uppercase tracking-wider text-muted">{{ $k }}</dt><dd class="mt-0.5 truncate text-sm font-medium">{{ $v }}</dd></div>@endforeach
                </dl>
            @endif

            {{-- Tabs --}}
            <div class="card mt-6 overflow-hidden">
                <div class="no-scrollbar flex gap-1 overflow-x-auto border-b border-line-soft bg-warm px-2">
                    @foreach($tabs as $k => $label)<button type="button" @click="tab = @js($k)" class="shrink-0 border-b-2 px-3 py-3 text-sm font-semibold transition-colors" :class="tab === @js($k) ? 'border-red text-red' : 'border-transparent text-muted hover:text-ink'">{{ $label }}</button>@endforeach
                </div>
                <div class="p-5 sm:p-6">
                    <div x-show="tab === 'description'" class="prose-h">{!! $product->details ?: '<p>'.e($product->description).'</p>' !!}@if($product->storage_instructions)<p><strong>Storage:</strong> {{ $product->storage_instructions }}</p>@endif</div>
                    @if($product->ingredients)<div x-show="tab === 'ingredients'" x-cloak class="prose-h"><p>{{ $product->ingredients }}</p></div>@endif
                    @if($nutrition)
                        <div x-show="tab === 'nutrition'" x-cloak>
                            <p class="text-xs text-muted">Approximate values per 100 g / 100 ml</p>
                            <table class="mt-3 w-full text-sm"><tbody class="divide-y divide-line-soft">@foreach($nutrition as $row)<tr><td class="py-2 text-muted">{{ $row['label'] }}</td><td class="py-2 text-right font-semibold tabular">{{ $row['value'] ?? '' }}</td></tr>@endforeach</tbody></table>
                        </div>
                    @endif
                    @if($benefits)<div x-show="tab === 'benefits'" x-cloak><ul class="space-y-2.5">@foreach($benefits as $b)<li class="flex gap-3 text-sm"><x-ico name="check" :size="16" :stroke="2" class="mt-0.5 shrink-0 text-gold" /> {{ $b }}</li>@endforeach</ul></div>@endif
                    @if($product->usage_instructions)<div x-show="tab === 'usage'" x-cloak class="prose-h"><p>{{ $product->usage_instructions }}</p></div>@endif
                    <div x-show="tab === 'shipping'" x-cloak class="prose-h">
                        <p><strong>Shipping:</strong> {{ $hs['delivery_note'] ?? 'Pan-India delivery' }}. Orders placed before 2 pm ship the same working day @if($threshold > 0), with free delivery on orders above {{ money($threshold) }} @endif. <a href="/shipping">Delivery details</a>.</p>
                        <p><strong>Returns:</strong> Damaged, leaking or not as described? Tell us within 7 days for a replacement or full refund. <a href="/returns">Read the policy</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if($completeTheLook->isNotEmpty())
        <section class="border-y border-line bg-warm"><div class="h-container section !py-10"><x-section-head eyebrow="Pairs well with" title="Frequently bought together" class="!mb-6" /><x-rail :products="$completeTheLook" /></div></section>
    @endif

    {{-- Reviews --}}
    <section id="reviews" class="h-container section !py-10">
        <div class="card grid gap-8 p-6 lg:grid-cols-12 lg:p-8">
            <div class="lg:col-span-4">
                <p class="eyebrow">Reviews</p>
                <h2 class="section-title mt-2 !text-2xl">What customers say</h2>
                <div class="mt-4 flex items-end gap-3"><p class="font-serif text-5xl font-semibold leading-none">{{ $product->review_count ? number_format($product->rating, 1) : 'New'}}</p><div><x-rating :value="$product->rating" :size="16" /><p class="mt-1 text-xs text-muted">{{ number_format($product->review_count) }} {{ Str::plural('review', $product->review_count) }}</p></div></div>
                <ul class="mt-4 space-y-1.5">@for($i = 5; $i >= 1; $i--)@php $n = $breakdown[$i] ?? 0; $pct = $breakdown->sum() ? round($n / $breakdown->sum() * 100) : 0; @endphp<li class="flex items-center gap-2 text-xs"><span class="w-5 font-semibold">{{ $i }}★</span><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-cream"><span class="block h-full rounded-full bg-gold" style="width: {{ $pct }}%"></span></span><span class="w-6 text-right text-muted">{{ $n }}</span></li>@endfor</ul>
                <div x-data="reviewForm()" class="mt-5">
                    <button type="button" @click="open = !open" class="btn btn-outline btn-block">Write a review</button>
                    @if(session('review_status'))<p class="mt-3 rounded-lg bg-cream p-3 text-sm text-maroon">{{ session('review_status') }}</p>@endif
                    <form x-show="open" x-collapse x-cloak method="post" action="{{ route('products.reviews.store', $product->slug) }}" class="mt-4 space-y-3">
                        @csrf
                        <div><span class="label">Your rating</span><div class="flex gap-1">@for($i = 1; $i <= 5; $i++)<button type="button" @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" class="text-star" aria-label="{{ $i }} stars"><svg width="26" height="26" viewBox="0 0 24 24" :fill="(hover || rating) >= {{ $i }} ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="m12 3 2.8 5.8 6.4.9-4.6 4.5 1.1 6.3L12 17.5l-5.7 3 1.1-6.3L2.8 9.7l6.4-.9L12 3z"/></svg></button>@endfor</div><input type="hidden" name="rating" :value="rating"></div>
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
                @if($reviews->isEmpty())<p class="text-sm text-muted">No reviews yet — be the first to share how you cooked with it.</p>@else
                    <ul class="divide-y divide-line-soft">
                        @foreach($reviews as $r)
                            <li class="py-4 first:pt-0">
                                <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-full bg-cream font-serif text-sm font-semibold text-red">{{ Str::upper(Str::substr($r->name, 0, 1)) }}</span><span class="text-sm font-semibold">{{ $r->name }}</span><span class="badge badge-soft">Verified</span></div><span class="text-xs text-muted">{{ $r->created_at->format('d M Y') }}</span></div>
                                <div class="mt-2 flex items-center gap-2"><x-rating :value="$r->rating" :size="13" />@if($r->title)<span class="text-sm font-semibold">{{ $r->title }}</span>@endif</div>
                                <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $r->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    @if($related->isNotEmpty())
        <section class="h-container section !pt-0"><x-section-head eyebrow="You may also like" title="Related products" :href="$product->category?->url" :label="'More in '.($product->category?->name ?? 'category')" class="!mb-6" /><x-rail :products="$related" /></section>
    @endif

    <div x-data="productRail('recent')" data-exclude="{{ $product->slug }}" x-show="items.length" x-cloak class="h-container section !pt-0">
        <x-section-head eyebrow="Recently viewed" title="Pick up where you left off" class="!mb-6" />
        <div x-data="rail()" class="relative"><div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0"><template x-for="p in items" :key="p.id"><div class="w-[11.5rem] shrink-0 sm:w-56" data-slide>@include('partials.card-dynamic')</div></template></div></div>
    </div>

    {{-- Sticky mobile add-to-cart --}}
    <div x-show="showSticky" x-cloak x-transition class="fixed inset-x-0 bottom-16 z-40 border-t border-line bg-warm p-3 shadow-float lg:hidden">
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $product->name }}</p><p class="font-serif text-base font-semibold tabular">{{ money($product->price) }} <span x-text="size" class="font-sans text-xs font-normal text-muted"></span></p></div>
            @if($product->in_stock)<button type="button" @click="add()" class="btn btn-primary">Add to cart</button>@endif
        </div>
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
