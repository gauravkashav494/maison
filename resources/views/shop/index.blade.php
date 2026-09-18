@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
@php
    $crumbs = ['Shop' => route('shop.index')];
    if ($category?->parent) $crumbs[$category->parent->name] = $category->parent->url;
    $crumbs[$title] = null;
    $init = $filters + ['view' => $view];
@endphp
<x-page-hero :eyebrow="$eyebrow" :title="$title" :description="$category?->description" :breadcrumbs="$crumbs" />

<section class="container-luxe py-10 lg:py-14" x-data="shopFilters(@js($init))" @open-filters.window="open = true">
    {{-- Category strip --}}
    <nav class="no-scrollbar -mx-5 flex gap-6 overflow-x-auto border-b border-ink/10 px-5 pb-5 text-[0.6875rem] uppercase tracking-[0.2em] lg:mx-0 lg:px-0" aria-label="Categories">
        <a href="{{ route('shop.index') }}" class="link-underline shrink-0 pb-0.5" data-active="{{ !$category && request()->is('shop') ? 'true' : 'false' }}">All</a>
        @foreach($categories as $c)
            <a href="{{ $c->url }}" class="link-underline shrink-0 pb-0.5" data-active="{{ ($category?->id === $c->id || $category?->parent_id === $c->id) ? 'true' : 'false' }}">{{ $c->name }}</a>
        @endforeach
        <a href="{{ route('shop.category', 'sale') }}" class="link-underline shrink-0 pb-0.5 text-rouge">Sale</a>
    </nav>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-4 py-5">
        <div class="flex items-center gap-4 text-[0.6875rem] uppercase tracking-[0.2em]">
            <button type="button" @click="open = true" class="flex items-center gap-2 border border-ink/20 px-4 py-2.5 lg:hidden">
                Filter & sort @if($activeCount)<span class="grid h-4 min-w-4 place-items-center rounded-full bg-ink px-1 text-[0.5625rem] text-ivory">{{ $activeCount }}</span>@endif
            </button>
            <span class="text-smoke tabular-nums">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('piece', $products->total()) }}</span>
            @if($activeCount)<button type="button" @click="clear()" class="link-underline hidden text-smoke hover:text-ink lg:inline">Clear all</button>@endif
        </div>
        <div class="hidden items-center gap-6 text-[0.6875rem] uppercase tracking-[0.2em] lg:flex">
            <label class="flex items-center gap-2">
                <span class="text-smoke">Sort</span>
                <select x-model="f.sort" @change="submit()" class="border-b border-ink/30 bg-transparent py-1 text-[0.6875rem] uppercase tracking-[0.2em] outline-none">
                    <option value="">Featured</option>
                    <option value="newest">Newest</option>
                    <option value="rating">Top rated</option>
                    <option value="price-asc">Price: low to high</option>
                    <option value="price-desc">Price: high to low</option>
                </select>
            </label>
            <div class="flex items-center gap-1 border-l border-ink/10 pl-6" role="group" aria-label="Layout">
                <button type="button" @click="f.view = 'grid'; submit()" aria-label="Grid view" class="grid h-8 w-8 place-items-center {{ $view === 'grid' ? 'text-ink' : 'text-taupe hover:text-ink' }}"><x-ico name="grid" :size="16" /></button>
                <button type="button" @click="f.view = 'list'; submit()" aria-label="List view" class="grid h-8 w-8 place-items-center {{ $view === 'list' ? 'text-ink' : 'text-taupe hover:text-ink' }}"><x-ico name="menu" :size="16" /></button>
            </div>
        </div>
    </div>

    <div class="grid gap-10 lg:grid-cols-12">
        {{-- Desktop filters --}}
        <aside class="hidden lg:col-span-3 lg:block">
            <div class="sticky top-28 space-y-7 pr-6">
                @include('shop.partials.filter-groups')
                <button type="button" @click="submit()" class="btn btn-primary w-full">Apply filters</button>
            </div>
        </aside>

        {{-- Results --}}
        <div class="lg:col-span-9">
            @if($products->isEmpty())
                <div class="py-24 text-center">
                    <p class="font-serif text-3xl">Nothing matches those filters.</p>
                    <p class="mt-3 text-sm text-smoke">Try removing a filter or two.</p>
                    <button type="button" @click="clear()" class="btn btn-outline mt-8">Clear all filters</button>
                </div>
            @elseif($view === 'list')
                <div class="divide-y divide-ink/10">
                    @foreach($products as $p)
                        <div class="grid grid-cols-[38%_1fr] gap-6 py-8 sm:grid-cols-[28%_1fr] lg:grid-cols-[22%_1fr]">
                            <a href="{{ $p->url }}" class="group relative aspect-[3/4] overflow-hidden bg-sand">
                                <img src="{{ $p->image_url }}" alt="{{ $p->name }}" loading="lazy" class="img-cover img-zoom">
                                @if($p->is_new)<span class="absolute left-3 top-3 bg-ivory/90 px-2.5 py-1 text-[0.5625rem] uppercase tracking-[0.2em]">New</span>@endif
                            </a>
                            <div class="flex flex-col" x-data="productCard(@js($p->toCard()))">
                                <p class="eyebrow text-[0.5625rem] text-taupe">{{ $p->category?->name }}@if($p->brand) · {{ $p->brand }}@endif</p>
                                <h2 class="mt-2 font-serif text-2xl leading-tight"><a href="{{ $p->url }}" class="link-underline">{{ $p->name }}</a></h2>
                                <x-rating :value="$p->rating" :count="$p->review_count" class="mt-2" />
                                <p class="mt-3 max-w-lg text-sm leading-relaxed text-smoke">{{ $p->description }}</p>
                                <div class="mt-auto flex flex-wrap items-center gap-x-6 gap-y-3 pt-5">
                                    <span class="tabular-nums {{ $p->is_on_sale ? 'text-rouge' : '' }}">{{ money($p->price) }}</span>
                                    @if($p->compare_at_price)<span class="text-sm text-smoke line-through">{{ money($p->compare_at_price) }}</span>@endif
                                    <div class="flex gap-1.5">@foreach(array_slice($p->colors ?? [], 0, 4) as $c)<span class="h-3 w-3 rounded-full ring-1 ring-ink/10" style="background:{{ $c['hex'] }}"></span>@endforeach</div>
                                    <button type="button" @click="$store.ui.showQuickView(product.slug)" class="btn btn-outline btn-sm ml-auto">Quick add</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:gap-x-6 lg:gap-y-14">
                    @foreach($products as $p)
                        <x-product-card :product="$p" :show-rating="true" />
                    @endforeach
                </div>
            @endif
            @if($products->hasPages())<div class="mt-14">{{ $products->links() }}</div>@endif
        </div>
    </div>

    {{-- Mobile filter / sort bottom sheet --}}
    <div x-cloak x-show="open" class="fixed inset-0 z-[70] lg:hidden" x-transition.opacity>
        <button type="button" class="absolute inset-0 bg-ink/40" @click="open = false" aria-label="Close filters"></button>
        <div x-show="open" x-trap.noscroll="open"
             x-transition:enter="transition duration-400 ease-[var(--ease-luxe)]" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition duration-300" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 flex max-h-[88dvh] flex-col bg-ivory" role="dialog" aria-modal="true" aria-label="Filter and sort">
            <div class="flex items-center justify-between border-b border-ink/10 px-5 py-4">
                <p class="font-serif text-2xl">Filter & sort</p>
                <button type="button" @click="open = false" class="-mr-2 grid h-10 w-10 place-items-center" aria-label="Close"><x-ico name="close" :size="20" /></button>
            </div>
            <div class="flex-1 overflow-y-auto px-5 py-4">
                <div class="mb-6">
                    <p class="eyebrow mb-3 text-taupe">Sort by</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['' => 'Featured', 'newest' => 'Newest', 'rating' => 'Top rated', 'price-asc' => 'Price ↑', 'price-desc' => 'Price ↓'] as $k => $l)
                            <button type="button" @click="f.sort = @js($k)" :class="f.sort === @js($k) ? 'border-ink bg-ink text-ivory' : 'border-ink/20'" class="border px-3 py-2 text-[0.6875rem] uppercase tracking-[0.15em]">{{ $l }}</button>
                        @endforeach
                    </div>
                </div>
                @include('shop.partials.filter-groups', ['mobile' => true])
            </div>
            <div class="flex gap-3 border-t border-ink/10 px-5 py-4 pb-safe">
                <button type="button" @click="clear()" class="btn btn-outline flex-1">Clear</button>
                <button type="button" @click="submit()" class="btn btn-primary flex-[2]">Show results</button>
            </div>
        </div>
    </div>
</section>
@endsection
