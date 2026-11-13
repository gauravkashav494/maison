@extends('layouts.app')

{{--
    The catalogue: categories down the side, products as a list to order down — one row per
    size with a quantity box, and "Add all to order" once anything is set.
    The shared ShopController supplies the paginated products, categories and filters.
--}}

@php
    $current = $category ?? null;
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
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $current ? $current->name : 'All products' }}</h1>
                    <p class="bk-muted mt-1 text-sm">
                        {{ $current?->tagline ?: 'Trade supplies at wholesale prices.' }}
                        · {{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}
                    </p>
                </div>
                {{-- A plain GET form: choosing an option reloads the page sorted (from page 1). The
                     button is only for browsers without JavaScript. --}}
                <form method="get" action="{{ url()->current() }}" class="flex items-center gap-2 text-sm" x-data>
                    <label for="bk-sort" class="bk-muted">Sort</label>
                    <select id="bk-sort" name="sort" class="bk-select !w-auto !py-2" @change="$el.form.submit()">
                        @foreach($sorts as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['sort'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="bk-btn bk-btn-outline !py-2">Sort</button></noscript>
                </form>
            </div>

            {{-- Categories as chips on smaller screens, where the side list is hidden. --}}
            <div class="bk-scroll-x -mx-1 mt-4 flex gap-2 px-1 lg:hidden">
                <a href="{{ route('shop.index') }}" @class(['bk-btn !py-2 text-xs', 'bk-btn-primary' => ! $current, 'bk-btn-outline' => $current])>All</a>
                @foreach($categories as $cat)
                    <a href="{{ $cat->url }}" @class(['bk-btn !py-2 text-xs', 'bk-btn-primary' => $current?->id === $cat->id, 'bk-btn-outline' => $current?->id !== $cat->id])>{{ $cat->name }}</a>
                @endforeach
            </div>

            @if($products->isEmpty())
                <div class="bk-card mt-5 p-10 text-center">
                    <x-heroicon-o-archive-box class="mx-auto size-10 text-brand" aria-hidden="true" />
                    <p class="mt-3 font-bold">Nothing here yet</p>
                    <p class="bk-muted mt-1 text-sm">Try another category, or search for a product code.</p>
                </div>
            @else
                <div class="mt-5">
                    {{-- Without a category, a heading row starts each category's rows — but only in the
                         recommended order: sorted by price or date the categories interleave. --}}
                    <x-product-list :products="$products" :group="! $current && ($filters['sort'] ?? '') === ''" :show-stock="(bool) tsetting('site.show_stock', true)" />
                </div>
                <div class="bk-pager mt-8">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
