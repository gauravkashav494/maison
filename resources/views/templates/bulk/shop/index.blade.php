@extends('layouts.app')

{{--
    The catalogue: categories down the side, products as a list to order down — one row per
    size with a quantity box, and "Add all to order" once anything is set.
    The shared ShopController supplies the paginated products, categories and filters.
--}}

@php
    $current = $category ?? null;
    $search = $filters['q'] ?? '';
    $sorts = ['' => 'Recommended', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'newest' => 'Newest'];
@endphp

@section('content')
<div class="bk-wrap mt-6">
    <nav class="bk-crumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a><span>/</span>
        @if($current)
            <a href="{{ route('shop.index') }}">All products</a><span>/</span><span class="text-[var(--bk-ink)]">{{ $current->name }}</span>
        @else
            <span class="text-[var(--bk-ink)]">All products</span>
        @endif
    </nav>

    <div class="bk-shop mt-4">
        <aside class="bk-card bk-side hidden p-2 lg:block" aria-label="Categories">
            <p class="px-3 pb-2 pt-2 text-xs font-extrabold uppercase tracking-wider bk-muted">Categories</p>
            <a href="{{ route('shop.index') }}" @class(['is-on' => ! $current])>All products</a>
            @foreach($categories as $cat)
                <a href="{{ $cat->url }}" @class(['is-on' => $current?->id === $cat->id])>{{ $cat->name }}</a>
            @endforeach
            <div class="m-2 mt-4 rounded-lg bg-brand-soft p-3 text-sm">
                <p class="font-bold text-brand-ink">Know your codes?</p>
                <p class="bk-muted mt-0.5 text-xs">Skip browsing — type or paste your list.</p>
                <a href="{{ url('/#bulk-order') }}" class="bk-link mt-2 inline-block">Bulk order →</a>
            </div>
        </aside>

        <div>
            {{-- Searching changes only the count and the list: the term stays in the box (× clears
                 it), so the heading keeps its size and the search box never moves. --}}
            <div class="flex flex-wrap items-end justify-between gap-3 md:flex-nowrap">
                <div class="min-w-0 md:flex-1">
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $current ? $current->name : 'All products' }}</h1>
                    <p id="bk-shop-count" class="bk-muted mt-1 text-sm" aria-live="polite">
                        {{-- The tagline is left off on phones, so the count stays on one line. --}}
                        <span class="hidden sm:inline">{{ $current?->tagline ?: 'Trade supplies at wholesale prices.' }} ·</span>
                        {{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}
                        {{-- Every size is its own row, so say how many rows the products make — but only when
                             they all fit on this page, or the number would count one page of many. --}}
                        @php $sizes = $products->lastPage() === 1 ? collect($products->items())->sum(fn ($p) => max(1, count(\App\Templates\Bulk\OrderLines::for($p)))) : 0; @endphp
                        @if($sizes > $products->total())
                            · {{ $sizes }} sizes
                        @endif
                    </p>
                </div>
                {{-- A plain GET form. Typing searches as you go (shopSearch fetches this page and swaps in
                     the list and count, so focus stays in the box); choosing a sort reloads the page from
                     page 1; each keeps the other. The search stays inside the category being browsed and
                     also matches variant codes. Without JavaScript, Enter searches and the Sort button sorts. --}}
                <form method="get" action="{{ url()->current() }}" role="search" class="flex w-full items-center gap-2 text-sm sm:w-auto md:flex-none"
                      x-data="shopSearch" @submit.prevent="run(true)">
                    <div class="bk-find min-w-0 flex-1 sm:w-72 sm:flex-none">
                        <x-heroicon-o-magnifying-glass aria-hidden="true" />
                        <label for="bk-shop-q" class="sr-only">Search {{ $current ? $current->name : 'products' }}</label>
                        <input id="bk-shop-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="bk-input"
                               placeholder="Search by name or code…" autocomplete="off" spellcheck="false"
                               x-ref="q" @input.debounce.350ms="run()" :aria-busy="busy">
                        <button type="button" class="bk-find-clear" x-show="filled" x-cloak @click="clear()" aria-label="Clear search">
                            <x-heroicon-o-x-mark aria-hidden="true" />
                        </button>
                    </div>
                    {{-- On phones Sort is an icon button beside the search: the real menu sits over it,
                         invisible, so a tap opens the phone's own picker. A dot shows a sort is set. --}}
                    <div @class(['bk-sort', 'is-set' => ($filters['sort'] ?? '') !== ''])>
                        <label for="bk-sort" class="bk-sort-label bk-muted">Sort</label>
                        <span class="bk-sort-icon" aria-hidden="true"><x-heroicon-o-arrows-up-down /></span>
                        <select id="bk-sort" name="sort" class="bk-select !py-2" @change="$el.form.submit()">
                            @foreach($sorts as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <noscript><button type="submit" class="bk-btn bk-btn-outline !py-2">Sort</button></noscript>
                </form>
            </div>

            {{-- Categories as chips on smaller screens, where the side list is hidden. The current one
                 is scrolled into view, so it is never cut off at the edge. --}}
            <div class="bk-scroll-x bk-chips -mx-1 mt-3 flex gap-1.5 px-1 lg:hidden"
                 x-data x-init="$el.querySelector('[aria-current]')?.scrollIntoView({ block: 'nearest', inline: 'center' })">
                <a href="{{ route('shop.index') }}" @class(['bk-chip', 'is-on' => ! $current]) @if(! $current) aria-current="page" @endif>All</a>
                @foreach($categories as $cat)
                    <a href="{{ $cat->url }}" @class(['bk-chip', 'is-on' => $current?->id === $cat->id]) @if($current?->id === $cat->id) aria-current="page" @endif>{{ $cat->name }}</a>
                @endforeach
            </div>

            <div id="bk-shop-results">
            @if($products->isEmpty())
                <div class="bk-card mt-5 p-10 text-center">
                    <x-heroicon-o-archive-box class="mx-auto size-10 text-brand" aria-hidden="true" />
                    @if($search)
                        <p class="mt-3 font-bold">No products match “{{ $search }}”</p>
                        <p class="bk-muted mt-1 text-sm">
                            Check the spelling or code{{ $current ? ', or search all products' : '' }}.
                            @if($current)<a href="{{ route('shop.index', ['q' => $search]) }}" class="bk-link">Search all products →</a>@endif
                        </p>
                    @else
                        <p class="mt-3 font-bold">Nothing here yet</p>
                        <p class="bk-muted mt-1 text-sm">Try another category, or search for a product code.</p>
                    @endif
                </div>
            @else
                <div class="mt-5">
                    {{-- Without a category, a heading row starts each category's rows — but only in the
                         recommended order: sorted by price or date the categories interleave. A search shows a
                         short flat list. --}}
                    <x-product-list :products="$products" :group="! $current && ($filters['sort'] ?? '') === '' && $search === ''" :show-stock="(bool) tsetting('site.show_stock', true)" />
                </div>
                <div class="bk-pager mt-8">{{ $products->links() }}</div>
            @endif
            </div>
        </div>
    </div>
</div>
@endsection
