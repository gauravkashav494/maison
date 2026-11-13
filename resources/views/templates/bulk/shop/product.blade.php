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
    $sheet = array_map(fn ($l) => \Illuminate\Support\Arr::only($l, ['key', 'product_id', 'options', 'price', 'max']), $lines);
@endphp

@section('content')
<div class="bk-wrap mt-6">
    <nav class="bk-crumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a><span>/</span>
        <a href="{{ route('shop.index') }}">All products</a>
        @if($product->category)<span>/</span><a href="{{ $product->category->url }}">{{ $product->category->name }}</a>@endif
    </nav>

    <div class="mt-4 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
        <div class="bk-card overflow-hidden">
            <x-art :category="$product->category" :image="$product->image_url" :alt="$product->name" class="!aspect-square !rounded-none" />
        </div>

        <div class="bk-card p-5 sm:p-7">
            <div class="flex flex-wrap gap-2">
                @if($product->is_new)<span class="bk-badge bk-badge-new">New</span>@endif
                @if($product->is_best_seller)<span class="bk-badge bk-badge-ok">Most ordered</span>@endif
            </div>
            <h1 class="mt-2 text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl">{{ $product->name }}</h1>
            <p class="bk-code mt-2">Code {{ $product->sku ?: '—' }}@if($product->brand) · {{ $product->brand }}@endif</p>
            @if($product->description)<p class="bk-muted mt-4 leading-relaxed">{{ $product->description }}</p>@endif

            @if(count($lines) === 1)
                {{-- One pack: price, stock and the same stepper as the card. --}}
                <div class="mt-6 border-t border-[var(--bk-line)] pt-6" x-data="tradeCard(@js($lines), @js($first['key']))">
                    <div class="flex flex-wrap items-baseline gap-x-3">
                        <span class="bk-price !text-3xl">{{ $first['price_formatted'] }}</span>
                        @if($first['list_price'] > $first['price'])
                            <span class="bk-was !text-base">{{ money($first['list_price']) }}</span>
                            <span class="bk-badge bk-badge-deal">{{ (int) round(($first['list_price'] - $first['price']) * 100 / $first['list_price']) }}% off</span>
                        @endif
                        @if($first['unit'])<span class="bk-muted text-sm">per {{ $first['unit'] }}</span>@endif
                    </div>
                    @if($showStock)
                        <p class="mt-2"><span class="bk-badge {{ $first['stock'] <= 0 ? 'bk-badge-out' : ($first['stock'] <= 10 ? 'bk-badge-low' : 'bk-badge-ok') }}">{{ $first['stock'] <= 0 ? 'Out of stock' : number_format($first['stock']).' in stock' }}</span></p>
                    @endif
                    @if($first['max'] > 0)
                        <div class="mt-5 flex flex-wrap gap-3">
                            <span class="bk-step !h-12">
                                <button type="button" @click="step(-1)" :disabled="qty <= 1" aria-label="Decrease quantity"><x-heroicon-o-minus /></button>
                                <input type="number" inputmode="numeric" min="1" max="{{ $first['max'] }}" x-model.number="qty" @change="step(0)" aria-label="Quantity">
                                <button type="button" @click="step(1)" :disabled="qty >= {{ $first['max'] }}" aria-label="Increase quantity"><x-heroicon-o-plus /></button>
                            </span>
                            <button type="button" class="bk-btn bk-btn-primary flex-1 !py-3 text-base" :disabled="busy" @click="add()">
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
                <div class="mt-6 border-t border-[var(--bk-line)] pt-6" x-data="orderSheet(@js($sheet))">
                    <p class="font-extrabold">Choose sizes and quantities</p>
                    <table class="bk-table is-stack mt-3">
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
                                                       x-model.number="qty[@js($key)]" @change="clamp(@js($key))" aria-label="Quantity of size {{ $line['label'] }}">
                                                <button type="button" @click="step(@js($key), 1)" :disabled="(qty[@js($key)] || 0) >= {{ $line['max'] }}" aria-label="Increase {{ $line['label'] }}"><x-heroicon-o-plus /></button>
                                            </span>
                                            <span class="bk-error" x-show="errors[@js($key)]" x-text="errors[@js($key)]" x-cloak></span>
                                            <span class="bk-ok block" x-show="added[@js($key)]" x-text="added[@js($key)] + ' added'" x-cloak></span>
                                        @else
                                            <span class="bk-out-text text-sm">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-brand-soft p-3">
                        <p class="text-sm">
                            <template x-if="filled.length"><span><strong x-text="units"></strong> units · about <strong x-text="estimate"></strong></span></template>
                            <template x-if="!filled.length"><span class="bk-muted">Set a quantity against each size you need.</span></template>
                        </p>
                        <button type="button" class="bk-btn bk-btn-primary" :disabled="busy || !filled.length" @click="addAll()">
                            <x-heroicon-o-shopping-cart aria-hidden="true" /><span x-text="busy ? 'Adding…' : 'Add to order'">Add to order</span>
                        </button>
                    </div>
                </div>
            @else
                {{-- Too many combinations to list: choose them with the shared picker. --}}
                <div class="mt-6 grid gap-4 border-t border-[var(--bk-line)] pt-6" x-data="productPage(@js($product->toCard()))">
                    <span class="bk-price !text-3xl">{{ money($product->price) }}</span>
                    <x-variant-options :product="$product" />
                    @if($product->stock > 0)
                        <div class="flex flex-wrap gap-3">
                            <span class="bk-step !h-12">
                                <button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity"><x-heroicon-o-minus /></button>
                                <input type="number" min="1" max="{{ \App\Templates\Bulk\OrderLines::MAX_PER_LINE }}" x-model.number="qty" aria-label="Quantity">
                                <button type="button" @click="qty = Math.min({{ \App\Templates\Bulk\OrderLines::MAX_PER_LINE }}, qty + 1)" aria-label="Increase quantity"><x-heroicon-o-plus /></button>
                            </span>
                            <button type="button" class="bk-btn bk-btn-primary flex-1 !py-3" :disabled="$store.cart.busy" @click="add()">Add to order</button>
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
        <section class="bk-card mt-6 p-5 leading-relaxed sm:p-7">{!! $product->details !!}</section>
    @endif

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
