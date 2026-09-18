@extends('layouts.app')

@section('content')
@php
    $gs = tsetting('site');
    $crumbs = [];
    if ($category?->parent) { $crumbs[$category->parent->name] = $category->parent->url; }
    $crumbs[$title] = null;
    $init = $filters + ['view' => $view];
    $sorts = ['' => 'Relevance', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'newest' => 'Newest first', 'rating' => 'Top rated'];
    $priceRanges = [[null, 49, 'Under ₹49'], [50, 99, '₹50 – ₹99'], [100, 199, '₹100 – ₹199'], [200, 499, '₹200 – ₹499'], [500, null, '₹500 & above']];
@endphp
<div x-data="shopFilters(@js($init))" @open-filters.window="open = true">
    <x-page-head :title="$title" :text="$category?->description" :breadcrumbs="$crumbs" />

    {{-- Sub-category chips --}}
    @if($facets['subcategories']->isNotEmpty())
        <div class="g-container -mb-2 mt-4">
            <div class="no-scrollbar flex gap-2 overflow-x-auto pb-2">
                @foreach($facets['subcategories'] as $sub)
                    <a href="{{ $sub->url }}" class="chip">{{ $sub->name }}</a>
                @endforeach
            </div>
        </div>
    @elseif(!$category && $facets['categories']->isNotEmpty())
        <div class="g-container -mb-2 mt-4">
            <div class="no-scrollbar flex gap-2 overflow-x-auto pb-2">
                @foreach($facets['categories'] as $c)
                    <a href="{{ $c->url }}" class="chip">{{ $c->name }}</a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="g-container grid gap-6 py-6 lg:grid-cols-[15rem_1fr]">
        {{-- Sidebar filters (desktop) --}}
        <aside class="hidden lg:block">
            <div class="card sticky top-[7.5rem] max-h-[calc(100vh-8.5rem)] overflow-y-auto p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-extrabold">Filters <span x-show="activeCount" x-cloak class="ml-1 rounded-full bg-leaf px-1.5 py-0.5 text-[0.625rem] text-white" x-text="activeCount"></span></p>
                    <button type="button" x-show="activeCount" x-cloak @click="clear()" class="text-xs font-semibold text-berry">Clear all</button>
                </div>
                @include('shop.partials.filter-groups', ['priceRanges' => $priceRanges])
                <button type="button" @click="submit()" class="btn btn-primary btn-block mt-4">Apply filters</button>
            </div>
        </aside>

        {{-- Results --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate"><strong class="text-ink">{{ $products->total() }}</strong> {{ Str::plural('product', $products->total()) }}</p>
                <div class="flex items-center gap-2">
                    @if(!empty($gs['show_veg_filter']))
                        <label class="chip cursor-pointer" :class="f.veg && 'chip-active'"><span class="veg-mark"></span> {{ $gs['veg_filter_label'] ?? 'Veg only' }}<input type="checkbox" class="sr-only" x-model="f.veg" @change="submit()"></label>
                    @endif
                    <button type="button" @click="open = true" class="chip lg:hidden"><x-ico name="filter" :size="14" /> Filters <span x-show="activeCount" x-cloak class="rounded-full bg-leaf px-1.5 text-[0.625rem] text-white" x-text="activeCount"></span></button>
                    <label class="relative">
                        <span class="sr-only">Sort by</span>
                        <select x-model="f.sort" @change="submit()" class="field h-9 py-0 pl-3 text-xs font-semibold">
                            @foreach($sorts as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
            </div>

            @if($products->isEmpty())
                <x-empty-state class="mt-6" icon="search" title="No products match" text="Try removing a filter or searching for something else.">
                    <button type="button" @click="clear()" class="btn btn-outline">Clear filters</button>
                </x-empty-state>
            @else
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                    @foreach($products as $product)
                        <x-product-card :product="$product" :priority="$loop->index < 4" />
                    @endforeach
                </div>
                @if($products->hasPages())<div class="mt-8">{{ $products->links('vendor.pagination.grocery') }}</div>@endif
            @endif
        </div>
    </div>

    {{-- Mobile filter sheet --}}
    <div x-show="open" x-cloak class="lg:hidden">
        <div class="overlay" x-show="open" x-transition.opacity @click="open = false"></div>
        <div class="sheet sheet-bottom" x-show="open" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full" x-trap.noscroll="open">
            <div class="sheet-handle"></div>
            <div class="flex items-center justify-between px-5 py-3">
                <p class="text-lg font-extrabold">Filters</p>
                <div class="flex items-center gap-3">
                    <button type="button" x-show="activeCount" x-cloak @click="clear()" class="text-xs font-semibold text-berry">Clear all</button>
                    <button type="button" @click="open = false" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-paper" aria-label="Close"><x-ico name="close" :size="20" /></button>
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
