@extends('layouts.app')

@section('content')
@php
    $hs = tsetting('site');
    $crumbs = [];
    if ($category?->parent) { $crumbs[$category->parent->name] = $category->parent->url; }
    $crumbs[$title] = null;
    $init = $filters + ['view' => $view];
    $sorts = ['' => 'Recommended', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'newest' => 'Newest first', 'rating' => 'Top rated'];
    $priceRanges = [[null, 199, 'Under ₹199'], [200, 499, '₹200 – ₹499'], [500, 999, '₹500 – ₹999'], [1000, null, '₹1,000 & above']];
    $subcategories = $category ? $category->children()->where('is_active', true)->get() : collect();
    $featured = $category ? \App\Models\Product::with('category')->active()->whereIn('category_id', [$category->id, ...$subcategories->pluck('id')->all()])->bestSellers()->orderBy('sort_order')->limit(4)->get() : collect();
    $noFilters = $activeCount === 0 && ! request()->has('page') && ! request()->has('sort');
@endphp
<div x-data="shopFilters(@js($init))">
    {{-- Category banner --}}
    <section class="relative overflow-hidden bg-maroon-deep text-cream">
        @if($category?->image_url)<img src="{{ $category->image_url }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-45">@endif
        <div class="absolute inset-0 bg-gradient-to-r from-maroon-deep/95 via-maroon-deep/70 to-maroon-deep/30"></div>
        <div class="h-container relative py-10 lg:py-14">
            <x-breadcrumbs :items="$crumbs" class="!text-cream/70 [&_a:hover]:!text-gold-light [&_span.font-medium]:!text-cream" />
            <p class="eyebrow mt-5 text-gold-light">{{ $eyebrow }}</p>
            <h1 class="display mt-2 text-3xl text-cream lg:text-5xl">{{ $title }}</h1>
            @if($category?->description)<p class="mt-3 max-w-2xl text-sm text-cream/85 lg:text-base">{{ $category->description }}</p>@endif
            <p class="mt-4 text-xs font-medium uppercase tracking-wider text-gold-light">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p>
        </div>
    </section>

    {{-- Sub-categories --}}
    @if($subcategories->isNotEmpty())
        <div class="h-container -mt-6 relative z-10">
            <div class="no-scrollbar flex gap-3 overflow-x-auto pb-2 sm:flex-wrap">
                @foreach($subcategories as $sub)
                    <a href="{{ $sub->url }}" class="card card-hover flex shrink-0 items-center gap-3 py-2 pl-2 pr-4">
                        <span class="h-11 w-11 overflow-hidden rounded-md bg-cream">@if($sub->image_url)<img src="{{ $sub->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span>
                        <span class="text-sm font-semibold">{{ $sub->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @elseif(!$category && $facets['categories']->isNotEmpty())
        <div class="h-container -mt-6 relative z-10"><div class="no-scrollbar flex gap-2 overflow-x-auto pb-2">@foreach($facets['categories'] as $c)<a href="{{ $c->url }}" class="chip !bg-warm">{{ $c->name }}</a>@endforeach</div></div>
    @endif

    {{-- Featured in category --}}
    @if($noFilters && $featured->count() >= 4)
        <section class="h-container pt-8">
            <x-section-head eyebrow="Customer favourites" :title="'Best of '.$title" class="!mb-5" />
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">@foreach($featured as $product)<x-product-card :product="$product" :priority="true" />@endforeach</div>
        </section>
    @endif

    <div class="h-container grid gap-6 py-8 lg:grid-cols-[16rem_1fr] lg:gap-8">
        {{-- Sidebar filters --}}
        <aside class="hidden lg:block">
            <div class="card sticky top-[8.5rem] max-h-[calc(100vh-9.5rem)] overflow-y-auto p-5">
                <div class="flex items-center justify-between">
                    <p class="font-serif text-lg">Refine <span x-show="activeCount" x-cloak class="ml-1 rounded-full bg-red px-1.5 py-0.5 font-sans text-[0.625rem] text-white" x-text="activeCount"></span></p>
                    <button type="button" x-show="activeCount" x-cloak @click="clear()" class="text-xs font-medium text-red hover:underline">Clear all</button>
                </div>
                @include('shop.partials.filter-groups', ['priceRanges' => $priceRanges])
                <button type="button" @click="submit()" class="btn btn-primary btn-block mt-4">Apply filters</button>
            </div>
        </aside>

        {{-- Results --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
                <p class="text-sm text-muted">Showing <strong class="text-ink">{{ $products->count() }}</strong> of {{ $products->total() }}</p>
                <div class="flex items-center gap-2">
                    @if(!empty($hs['show_diet_filter']) && $facets['diets']->isNotEmpty())
                        <div class="hidden gap-1.5 xl:flex">
                            @foreach($facets['diets']->take(4) as $tag => $n)<button type="button" @click="toggle('diet', @js($tag)); submit()" class="chip !py-1.5 text-xs" :class="has('diet', @js($tag)) && 'chip-active'">{{ $tag }}</button>@endforeach
                        </div>
                    @endif
                    <button type="button" @click="open = true" class="chip lg:hidden"><x-ico name="filter" :size="14" /> Filters <span x-show="activeCount" x-cloak class="rounded-full bg-red px-1.5 text-[0.625rem] text-white" x-text="activeCount"></span></button>
                    <label class="relative"><span class="sr-only">Sort by</span><select x-model="f.sort" @change="submit()" class="field h-10 py-0 pl-3 text-xs font-semibold">@foreach($sorts as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
                </div>
            </div>

            @if($products->isEmpty())
                <x-empty-state class="mt-6" icon="search" title="No products match these filters" text="Try removing a filter or exploring another category.">
                    <button type="button" @click="clear()" class="btn btn-outline">Clear filters</button>
                </x-empty-state>
            @else
                <div class="mt-5 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach($products as $product)<x-product-card :product="$product" :priority="$loop->index < 4" />@endforeach
                </div>
                @if($products->hasPages())<div class="mt-10">{{ $products->links('vendor.pagination.heritage') }}</div>@endif
            @endif

            {{-- SEO content --}}
            @if($category?->content && $noFilters)
                <div class="mt-12 border-t border-line pt-8">
                    <p class="eyebrow">About {{ $category->name }}</p>
                    <div class="prose-h mt-3 max-w-3xl">{!! $category->content !!}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Mobile filter drawer --}}
    <div x-show="open" x-cloak class="lg:hidden">
        <div class="overlay" x-show="open" x-transition.opacity @click="open = false"></div>
        <div class="sheet sheet-bottom" x-show="open" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full" x-trap.noscroll="open">
            <div class="sheet-handle"></div>
            <div class="flex items-center justify-between px-5 py-3">
                <p class="font-serif text-xl">Refine</p>
                <div class="flex items-center gap-3"><button type="button" x-show="activeCount" x-cloak @click="clear()" class="text-xs font-medium text-red">Clear all</button><button type="button" @click="open = false" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-cream" aria-label="Close"><x-ico name="close" :size="20" /></button></div>
            </div>
            <div class="flex-1 overflow-y-auto px-5 pb-4">@include('shop.partials.filter-groups', ['priceRanges' => $priceRanges])</div>
            <div class="border-t border-line p-4 safe-bottom"><button type="button" @click="submit()" class="btn btn-primary btn-block btn-lg">Show results</button></div>
        </div>
    </div>
</div>
@endsection
