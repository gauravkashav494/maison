@extends('layouts.app')

{{--
    Product page. One pack size: a stepper and Add, like the card. Several sizes: a table with
    every size's code, price and stock and its own quantity box, so a buyer can order 10 × M and
    5 × L in one go. A product with too many combinations to list is ordered through the shared
    option picker instead.
--}}

@php
    $lines = \App\Templates\Bulk\OrderLines::for($product);
    $first = collect($lines)->first(fn ($l) => $l['max'] > 0) ?? ($lines[0] ?? null);
    $showStock = (bool) tsetting('site.show_stock', true);
    $sheet = array_map(fn ($l) => \Illuminate\Support\Arr::only($l, ['key', 'product_id', 'options', 'price', 'max', 'limit_message']), $lines);
@endphp

@section('content')
<div class="bk-wrap mt-6">
    <nav class="bk-crumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a><span>/</span>
        <a href="{{ route('shop.index') }}">All products</a>
        @if($product->category)<span>/</span><a href="{{ $product->category->url }}">{{ $product->category->name }}</a>@endif
    </nav>

    <div class="bk-compact mt-4 grid items-start gap-4 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)]">
        <div class="bk-card overflow-hidden">
            <div class="relative">
                <x-art :category="$product->category" :image="$product->image_url" :alt="$product->name" class="!aspect-square !rounded-none" />
                {{-- The chosen colour's photo (Variants → Colour photos), over the product's own. --}}
                <x-card-colour-photo :product="$product" />
                <x-wishlist-button :product="$product" class="bk-heart-corner" />
            </div>
            {{-- Always on the product page (with or without photos); a colour with photos can be clicked to show them. --}}
            <x-card-colours :product="$product" :select="true" :with-photos-only="false" :max="12" class="is-large border-t border-[var(--bk-line)] p-4" />
        </div>

        <div class="bk-card p-4 sm:p-5">
            <div class="flex flex-wrap gap-2">
                @if($product->is_new)<span class="bk-badge bk-badge-new">New</span>@endif
                @if($product->is_best_seller)<span class="bk-badge bk-badge-ok">Most ordered</span>@endif
            </div>
            <h1 class="mt-1.5 text-xl font-extrabold leading-tight tracking-tight sm:text-2xl">{{ $product->name }}</h1>
            <p class="bk-code mt-1">Code {{ $product->sku ?: '—' }}@if($product->brand) · <a href="{{ \App\Models\Brand::urlFor($product->brand) }}" class="hover:underline">{{ $product->brand }}</a>@endif</p>
            @module('reviews')
                <a href="#reviews" class="mt-1 inline-flex items-center gap-1.5 text-sm no-underline">
                    @if($product->review_count > 0)
                        <x-rating :value="$product->rating" :size="14" style="color: var(--bk-accent)" />
                        <span class="font-bold">{{ number_format($product->rating, 1) }}</span>
                        <span class="bk-muted">({{ number_format($product->review_count) }} {{ Str::plural('review', $product->review_count) }})</span>
                    @else
                        <span class="bk-link">Be the first to review</span>
                    @endif
                </a>
            @endmodule
            @if($product->description)<p class="bk-muted mt-3 text-sm leading-relaxed">{{ $product->description }}</p>@endif

            @if(count($lines) === 1)
                {{-- One pack: price, stock and the same stepper as the card. --}}
                <div class="mt-4 border-t border-[var(--bk-line)] pt-4" x-data="tradeCard(@js($lines), @js($first['key']))">
                    <div class="flex flex-wrap items-baseline gap-x-3">
                        <span class="bk-price !text-2xl">{{ $first['price_formatted'] }}</span>
                        @if($first['list_price'] > $first['price'])
                            <span class="bk-was !text-base">{{ money($first['list_price']) }}</span>
                            <span class="bk-badge bk-badge-deal">{{ (int) round($first['discount_percent']) }}% off</span>
                        @endif
                        @if($first['unit'])<span class="bk-muted text-sm">per {{ $first['unit'] }}</span>@endif
                    </div>
                    @if($showStock)
                        <p class="mt-2"><span class="bk-badge {{ $first['stock'] <= 0 ? 'bk-badge-out' : ($first['stock'] <= 10 ? 'bk-badge-low' : 'bk-badge-ok') }}">{{ $first['stock'] <= 0 ? 'Out of stock' : number_format($first['stock']).' in stock' }}</span></p>
                    @endif
                    @if($first['max'] > 0)
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="bk-step !h-9">
                                <button type="button" @click="step(-1)" :disabled="qty <= 1" aria-label="Decrease quantity"><x-heroicon-o-minus /></button>
                                <input type="number" inputmode="numeric" min="1" max="{{ $first['max'] }}" x-model.number="qty" @change="step(0)" aria-label="Quantity">
                                <button type="button" @click="step(1)" aria-label="Increase quantity"><x-heroicon-o-plus /></button>
                            </span>
                            <button type="button" class="bk-btn bk-btn-primary flex-1" :disabled="busy" @click="add()">
                                <x-heroicon-o-shopping-cart aria-hidden="true" /><span x-text="busy ? 'Adding…' : 'Add to order'">Add to order</span>
                            </button>
                        </div>
                        <p class="bk-error" x-show="error" x-text="error" x-cloak></p>
                        <p class="bk-muted mt-2 text-xs">Up to {{ $first['max'] }} per order line.</p>
                    @else
                        <p class="bk-out-text mt-4 text-sm">Out of stock right now — call the trade desk for the next delivery date.</p>
                    @endif
                </div>
            @elseif(count($lines) > 1)
                {{-- Several sizes: one row each, ordered together. --}}
                <div class="mt-4 border-t border-[var(--bk-line)] pt-4" x-data="orderSheet(@js($sheet))">
                    <p class="text-sm font-extrabold">Choose sizes and quantities</p>
                    <table class="bk-table is-stack mt-2">
                        <thead>
                            <tr>
                                <th scope="col">Size</th>
                                <th scope="col">Code</th>
                                <th scope="col" class="is-num">Price</th>
                                @if($showStock)<th scope="col">Stock</th>@endif
                                <th scope="col">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                @php $key = $line['key']; @endphp
                                <tr>
                                    <td class="font-bold" data-label="Size">{{ $line['label'] ?? '—' }}</td>
                                    <td class="bk-code" data-label="Code">{{ $line['sku'] ?: '—' }}</td>
                                    <td class="is-num font-bold" data-label="Price">{{ $line['price_formatted'] }}</td>
                                    @if($showStock)
                                        <td data-label="Stock"><span class="bk-badge {{ $line['stock'] <= 0 ? 'bk-badge-out' : ($line['stock'] <= 10 ? 'bk-badge-low' : 'bk-badge-ok') }}">{{ $line['stock'] <= 0 ? 'Out of stock' : number_format($line['stock']).' in stock' }}</span></td>
                                    @endif
                                    <td data-label="Qty">
                                        @if($line['max'] > 0)
                                            <span class="bk-step">
                                                <button type="button" @click="step(@js($key), -1)" :disabled="!(qty[@js($key)] > 0)" aria-label="Decrease {{ $line['label'] }}"><x-heroicon-o-minus /></button>
                                                <input type="number" inputmode="numeric" min="0" max="{{ $line['max'] }}" placeholder="0"
                                                       x-model.number="qty[@js($key)]" @change="change(@js($key))" @keydown.enter.prevent="$el.blur()" aria-label="Quantity of size {{ $line['label'] }}">
                                                <button type="button" @click="step(@js($key), 1)" aria-label="Increase {{ $line['label'] }}"><x-heroicon-o-plus /></button>
                                            </span>
                                            <span class="bk-error" x-show="errors[@js($key)]" x-text="errors[@js($key)]" x-cloak></span>
                                        @else
                                            <span class="bk-out-text text-sm">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-brand-soft px-3 py-2">
                        <p class="text-sm">
                            <template x-if="$store.cart.count > 0"><span><strong x-text="$store.cart.count"></strong> units in cart · <strong x-text="$store.cart.subtotal_formatted"></strong></span></template>
                            <template x-if="!($store.cart.count > 0)"><span class="bk-muted">Set a quantity against each size you need. It goes straight into your cart.</span></template>
                        </p>
                        <a href="{{ route('cart') }}" class="bk-btn bk-btn-primary" @click.prevent="$store.ui.openCart()">
                            <x-heroicon-o-shopping-cart aria-hidden="true" /><span>View cart</span>
                        </a>
                    </div>
                </div>
            @else
                {{-- Too many combinations to list: choose them with the shared picker. --}}
                <div class="mt-4 grid gap-3 border-t border-[var(--bk-line)] pt-4" x-data="productPage(@js($product->toCard()))"
                     {{-- Choosing a colour in the picker shows its photo in the picture above. --}}
                     x-effect="const c = Object.entries(options).find(([k, v]) => /^colou?rs?$/i.test(k) && v); $dispatch('card-colour', { id: {{ $product->id }}, src: c ? (@js(collect($product->cardColours())->pluck('image', 'name')->filter())[c[1]] ?? null) : null })">
                    <span class="bk-price !text-2xl">{{ money($product->price) }}</span>
                    <x-variant-options :product="$product" />
                    @if($product->stock > 0)
                        <div class="flex flex-wrap gap-2">
                            <span class="bk-step !h-9">
                                <button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity"><x-heroicon-o-minus /></button>
                                <input type="number" min="1" @if($product->max_qty) max="{{ $product->max_qty }}" @endif x-model.number="qty" aria-label="Quantity">
                                <button type="button" @click="incQty()" aria-label="Increase quantity"><x-heroicon-o-plus /></button>
                            </span>
                            <button type="button" class="bk-btn bk-btn-primary flex-1" :disabled="$store.cart.busy" @click="add()">Add to order</button>
                        </div>
                        <p class="bk-muted text-xs">Choose every option above before adding.</p>
                    @else
                        <p class="bk-out-text text-sm">Out of stock right now — call the trade desk for the next delivery date.</p>
                    @endif
                </div>
            @endif

        </div>
    </div>

    @if($product->details)
        <section class="bk-card mt-4 p-4 text-sm leading-relaxed sm:p-5">{!! $product->details !!}</section>
    @endif

    @module('reviews')
        <section id="reviews" class="bk-compact bk-card mt-4 grid gap-5 p-4 sm:p-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
            <div>
                <h2 class="text-base font-extrabold tracking-tight">Ratings &amp; reviews</h2>
                <div class="mt-2 flex items-end gap-3">
                    <p class="text-3xl font-extrabold leading-none">{{ $product->review_count ? number_format($product->rating, 1) : 'New' }}</p>
                    <div>
                        <x-rating :value="$product->rating" :size="16" style="color: var(--bk-accent)" />
                        <p class="bk-muted mt-1 text-xs">{{ number_format($product->review_count) }} {{ Str::plural('review', $product->review_count) }}</p>
                    </div>
                </div>
                <ul class="mt-3 space-y-1">
                    @for($i = 5; $i >= 1; $i--)
                        @php $n = $breakdown[$i] ?? 0; $pct = $breakdown->sum() ? round($n / $breakdown->sum() * 100) : 0; @endphp
                        <li class="flex items-center gap-2 text-xs">
                            <span class="w-5 font-bold">{{ $i }}★</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full" style="background: var(--bk-line)"><span class="block h-full rounded-full" style="width: {{ $pct }}%; background: var(--bk-accent)"></span></span>
                            <span class="bk-muted w-6 text-right">{{ $n }}</span>
                        </li>
                    @endfor
                </ul>

                <div x-data="{ open: {{ $errors->hasAny(['rating', 'name', 'email', 'title', 'body']) ? 'true' : 'false' }}, rating: {{ (int) old('rating', 5) }}, hover: 0 }" class="mt-4">
                    <button type="button" @click="open = !open" class="bk-btn bk-btn-outline bk-btn-block">Write a review</button>
                    @if(session('review_status'))<p class="bk-badge-ok mt-3 rounded-lg p-3 text-sm">{{ session('review_status') }}</p>@endif
                    <form x-show="open" x-collapse x-cloak method="post" action="{{ route('products.reviews.store', $product->slug) }}" class="mt-4 grid gap-3" novalidate>
                        @csrf
                        <div>
                            <span class="bk-label">Your rating<span class="bk-req">*</span></span>
                            <div class="mt-1 flex gap-1" style="color: var(--bk-accent)">
                                @for($i = 1; $i <= 5; $i++)
                                    <button type="button" @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" aria-label="{{ $i }} {{ Str::plural('star', $i) }}"><svg width="26" height="26" viewBox="0 0 24 24" :fill="(hover || rating) >= {{ $i }} ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m12 3 2.8 5.8 6.4.9-4.6 4.5 1.1 6.3L12 17.5l-5.7 3 1.1-6.3L2.8 9.7l6.4-.9L12 3z"/></svg></button>
                                @endfor
                            </div>
                            <input type="hidden" name="rating" :value="rating">
                            @error('rating')<span class="bk-error">{{ $message }}</span>@enderror
                        </div>
                        <label class="bk-label">Name<span class="bk-req">*</span>
                            <input name="name" autocomplete="name" class="bk-input" value="{{ old('name', auth()->user()?->name) }}">
                            @error('name')<span class="bk-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="bk-label">Email (not published)
                            <input name="email" type="email" autocomplete="email" class="bk-input" value="{{ old('email', auth()->user()?->email) }}">
                            @error('email')<span class="bk-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="bk-label">Title
                            <input name="title" class="bk-input" value="{{ old('title') }}">
                            @error('title')<span class="bk-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="bk-label">Review<span class="bk-req">*</span>
                            <textarea name="body" rows="4" class="bk-textarea">{{ old('body') }}</textarea>
                            @error('body')<span class="bk-error">{{ $message }}</span>@enderror
                        </label>
                        <div><button type="submit" class="bk-btn bk-btn-primary">Submit review</button></div>
                    </form>
                </div>
            </div>

            <div>
                @if($reviews->isEmpty())
                    <p class="bk-muted text-sm">No reviews yet. Bought this before? Tell other buyers how it worked out.</p>
                @else
                    <ul class="divide-y divide-[var(--bk-line)]">
                        @foreach($reviews as $r)
                            <li class="py-3 text-sm first:pt-0">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold">{{ $r->name }}</span>
                                        @if($r->is_verified_purchase)<span class="bk-badge bk-badge-ok">Verified buyer</span>@endif
                                    </div>
                                    <span class="bk-muted text-xs">{{ $r->created_at->format('d M Y') }}</span>
                                </div>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <x-rating :value="$r->rating" :size="13" style="color: var(--bk-accent)" />
                                    @if($r->title)<span class="text-sm font-bold">{{ $r->title }}</span>@endif
                                </div>
                                <p class="bk-muted mt-1.5 text-sm leading-relaxed">{{ $r->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endmodule

    @if(($related ?? collect())->isNotEmpty())
        <section class="mt-12">
            <div class="bk-section-head"><div><h2>Often ordered with this</h2></div></div>
            <div class="bk-grid is-wide">
                @foreach($related->take(4) as $item)
                    <x-trade-card :product="$item" :show-stock="$showStock" />
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
