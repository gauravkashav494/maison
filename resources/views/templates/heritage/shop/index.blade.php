@extends('layouts.app')

@section('content')
@php
    $hs = tsetting('site');
    $crumbs = [];
    if ($category?->parent) { $crumbs[$category->parent->name] = $category->parent->url; }
    $crumbs[$title] = null;
    $init = $filters + ['view' => $view];
    $sorts = ['' => 'Best selling', 'price-asc' => 'Price, low to high', 'price-desc' => 'Price, high to low', 'newest' => 'Date, new to old', 'rating' => 'Top rated'];
    $priceRanges = [[null, 199, 'Under ₹199'], [200, 499, '₹200 – ₹499'], [500, 999, '₹500 – ₹999'], [1000, null, '₹1,000 & above']];
    $subcategories = $category ? $category->children()->where('is_active', true)->get() : collect();
    $from = ($products->currentPage() - 1) * $products->perPage() + 1;
    $to = min($products->total(), $products->currentPage() * $products->perPage());
@endphp
<div x-data="shopFilters(@js($init))" class="h-container pt-5" @open-filters.window="open = true">
    <x-breadcrumbs :items="$crumbs" />
    <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="display text-2xl text-red lg:text-3xl">{{ $title }}</h1>@if($category?->description)<p class="mt-1 max-w-2xl text-sm text-muted">{{ $category->description }}</p>@endif</div>
    </div>

    {{-- Sub-categories --}}
    @if($subcategories->isNotEmpty())
        <div class="no-scrollbar mt-4 flex gap-2 overflow-x-auto pb-1">@foreach($subcategories as $sub)<a href="{{ $sub->url }}" class="pill-tab">{{ $sub->name }}</a>@endforeach</div>
    @endif

    {{-- Filter / sort bar --}}
    <div class="mt-5 flex flex-wrap items-center gap-4 border-b border-line pb-4">
        <button type="button" @click="open = true" class="flex items-center gap-1.5 font-serif text-base font-semibold lg:pointer-events-none"><span>Filter:</span><x-ico name="filter" :size="16" class="text-ink" /><span x-show="activeCount" x-cloak class="rounded-full bg-red px-1.5 text-[0.625rem] font-sans text-white" x-text="activeCount"></span></button>
        <span class="h-5 w-px bg-line"></span>
        <label class="flex items-center gap-2 text-sm"><span class="font-serif text-base font-semibold">Sort by:</span><select x-model="f.sort" @change="submit()" class="border-0 bg-transparent py-1 pr-6 text-sm text-ink focus:outline-none">@foreach($sorts as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
        @if(!empty($hs['show_diet_filter']) && $facets['diets']->isNotEmpty())
            <div class="ml-auto hidden gap-1.5 xl:flex">@foreach($facets['diets']->take(4) as $tag => $n)<button type="button" @click="toggle('diet', @js($tag)); submit()" class="pill-tab !py-1 text-xs" :class="has('diet', @js($tag)) && 'is-active'">{{ $tag }}</button>@endforeach</div>
        @endif
    </div>

    <div class="grid gap-6 py-6 lg:grid-cols-[15rem_1fr] lg:gap-8">
        {{-- Sidebar --}}
        <aside class="hidden lg:block">
            <div class="sticky top-[9rem] rounded-2xl bg-cream-dark p-5">
                <div class="flex items-center justify-between"><p class="font-serif text-lg font-semibold">Filters</p><button type="button" x-show="activeCount" x-cloak @click="clear()" class="text-xs font-medium text-red hover:underline">Clear all</button></div>
                @include('shop.partials.filter-groups', ['priceRanges' => $priceRanges])
                <button type="button" @click="submit()" class="btn btn-primary btn-block mt-4 rounded-full">Apply</button>
            </div>
        </aside>

        {{-- Grid --}}
        <div>
            <p class="text-sm font-medium">Showing {{ $products->total() ? $from."–".$to : 0 }} of {{ $products->total() }} products</p>
            @if($products->isEmpty())
                <x-empty-state class="mt-6" icon="search" title="No products match these filters" text="Try removing a filter or exploring another category."><button type="button" @click="clear()" class="btn btn-outline">Clear filters</button></x-empty-state>
            @else
                <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">@foreach($products as $product)<x-product-card :product="$product" :priority="$loop->index < 4" />@endforeach</div>
                @if($products->hasPages())<div class="mt-10">{{ $products->links('vendor.pagination.heritage') }}</div>@endif
            @endif
            @if($category?->content && $activeCount === 0 && ! request()->has('page'))
                <div class="mt-12 rounded-2xl bg-cream-dark p-6 lg:p-8"><h2 class="font-serif text-xl font-semibold text-maroon">About {{ $category->name }}</h2><div class="prose-h mt-3 max-w-3xl">{!! $category->content !!}</div></div>
            @endif
        </div>
    </div>

    {{-- Mobile filter drawer --}}
    <div x-show="open" x-cloak class="lg:hidden">
        <div class="overlay" x-show="open" x-transition.opacity @click="open = false"></div>
        <div class="sheet sheet-bottom" x-show="open" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full" x-trap.noscroll="open">
            <div class="sheet-handle"></div>
            <div class="flex items-center justify-between px-5 py-3"><p class="font-serif text-xl">Filters</p><div class="flex items-center gap-3"><button type="button" x-show="activeCount" x-cloak @click="clear()" class="text-xs font-medium text-red">Clear all</button><button type="button" @click="open = false" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-cream" aria-label="Close"><x-ico name="close" :size="20" /></button></div></div>
            <div class="flex-1 overflow-y-auto px-5 pb-4">@include('shop.partials.filter-groups', ['priceRanges' => $priceRanges])</div>
            <div class="border-t border-line p-4 safe-bottom"><button type="button" @click="submit()" class="btn btn-primary btn-block btn-lg rounded-full">Show results</button></div>
        </div>
    </div>
</div>
@endsection
