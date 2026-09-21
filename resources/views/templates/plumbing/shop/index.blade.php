@extends('layouts.app')

@section('content')
@php
    $crumbs = [];
    if ($category?->parent) { $crumbs[$category->parent->name] = $category->parent->url; }
    $crumbs[$title] = null;
    $init = $filters + ['view' => $view];
    $sorts = ['' => 'Relevance', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'newest' => 'Newest first', 'rating' => 'Top rated'];
    $priceRanges = [[null, 199, 'Under ₹199'], [200, 499, '₹200 – ₹499'], [500, 999, '₹500 – ₹999'], [1000, 4999, '₹1,000 – ₹4,999'], [5000, null, '₹5,000 & above']];
    $chips = $facets['subcategories']->isNotEmpty() ? $facets['subcategories'] : (! $category ? $facets['categories'] : collect());
@endphp
<div x-data="shopFilters(@js($init))" @open-filters.window="open = true">
    {{-- Category header: title + description, with the category image on desktop --}}
    <div class="border-b border-line bg-white">
        <div class="p-container flex items-center justify-between gap-6 py-4 lg:py-6">
            <div class="min-w-0">
                <x-breadcrumbs :items="$crumbs" />
                <h1 class="mt-1 font-display text-xl font-bold lg:text-3xl">{{ $title }}</h1>
                @if($category?->description)<p class="mt-1 max-w-2xl text-sm text-slate">{{ $category->description }}</p>@endif
                <p class="mt-2 text-xs font-semibold text-slate"><span class="text-ink">{{ number_format($products->total()) }}</span> {{ Str::plural('product', $products->total()) }}@if($category?->parent) in {{ $category->parent->name }}@endif</p>
            </div>
            @if($category?->image_url)<img src="{{ $category->image_url }}" alt="" class="hidden h-24 w-36 shrink-0 rounded-xl object-cover lg:block">@endif
        </div>
        @if($chips->isNotEmpty())
            <div class="p-container">
                <div class="no-scrollbar flex gap-2 overflow-x-auto pb-3">
                    @foreach($chips as $c)<a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>@endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="p-container grid gap-6 py-5 lg:grid-cols-[16rem_1fr] lg:py-8">
        {{-- Sidebar filters (desktop) --}}
        <aside class="hidden lg:block">
            <div class="card sticky top-[8.75rem] max-h-[calc(100vh-9.5rem)] overflow-y-auto p-4">
                <div class="flex items-center justify-between">
                    <p class="font-display text-sm font-bold">Filters <span x-show="activeCount" x-cloak class="ml-1 rounded-full bg-primary px-1.5 py-0.5 text-[0.625rem] text-white" x-text="activeCount"></span></p>
                    <button type="button" x-show="activeCount" x-cloak @click="clear()" class="text-xs font-semibold text-danger">Clear all</button>
                </div>
                @include('shop.partials.filter-groups', ['priceRanges' => $priceRanges])
                <button type="button" @click="submit()" class="btn btn-primary btn-block mt-4">Apply filters</button>
            </div>
        </aside>

        {{-- Results --}}
        <div class="min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="open = true" class="chip lg:hidden"><x-ico name="filter" :size="14" /> Filters <span x-show="activeCount" x-cloak class="rounded-full bg-primary px-1.5 text-[0.625rem] text-white" x-text="activeCount"></span></button>
                    <label class="chip cursor-pointer" :class="f.sale && 'chip-active'"><x-ico name="percent" :size="14" /> On offer<input type="checkbox" class="sr-only" x-model="f.sale" @change="submit()"></label>
                    <label class="chip cursor-pointer" :class="f.availability === 'in-stock' && 'chip-active'"><x-ico name="check" :size="14" /> In stock<input type="checkbox" class="sr-only" :checked="f.availability === 'in-stock'" @change="f.availability = $event.target.checked ? 'in-stock' : ''; submit()"></label>
                </div>
                <label class="relative">
                    <span class="sr-only">Sort by</span>
                    <select x-model="f.sort" @change="submit()" class="field h-10 py-0 pl-3 pr-8 text-xs font-semibold">
                        @foreach($sorts as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>

            @if($products->isEmpty())
                <x-empty-state class="mt-6" icon="search" title="No products match" text="Try removing a filter, or search by size (1 inch), material (CPVC) or brand.">
                    <button type="button" @click="clear()" class="btn btn-outline">Clear filters</button>
                </x-empty-state>
            @else
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                @if($products->hasPages())<div class="mt-8">{{ $products->links('vendor.pagination.plumbing') }}</div>@endif
            @endif

            @if($category?->content)
                <div class="prose-p card mt-8 p-5 lg:p-6">{!! $category->content !!}</div>
            @endif
        </div>
    </div>

    {{-- Mobile filter sheet --}}
    <div x-show="open" x-cloak class="lg:hidden">
        <div class="overlay" x-show="open" x-transition.opacity @click="open = false"></div>
        <div class="sheet inset-x-0 bottom-0 flex max-h-[88dvh] flex-col rounded-t-2xl" x-show="open" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full" x-trap.noscroll="open" role="dialog" aria-modal="true" aria-label="Filters">
            <div class="mx-auto mt-2 h-1 w-10 rounded-full bg-line"></div>
            <div class="flex items-center justify-between px-5 py-3">
                <p class="font-display text-lg font-bold">Filters</p>
                <div class="flex items-center gap-3">
                    <button type="button" x-show="activeCount" x-cloak @click="clear()" class="text-xs font-semibold text-danger">Clear all</button>
                    <button type="button" @click="open = false" class="icon-btn" aria-label="Close"><x-ico name="close" :size="20" /></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto px-5 pb-4">
                @include('shop.partials.filter-groups', ['priceRanges' => $priceRanges])
            </div>
            <div class="border-t border-line p-4 safe-bottom">
                <button type="button" @click="submit()" class="btn btn-primary btn-block btn-lg">Show results</button>
            </div>
        </div>
    </div>
</div>
@endsection
