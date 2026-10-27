@extends('layouts.app')

{{--
    The home page is the list: a client selling from a price list has no use for a hero.
    The products come from the shared home controller (best sellers first, then the rest of
    the catalogue), so no extra query is needed here.
--}}

@php
    $products = \App\Models\Product::with('category')->active()
        ->orderBy('sort_order')->orderBy('name')
        ->paginate((int) setting('site.per_page', 50));
@endphp

@section('content')
<div class="ol-page">
    <div class="ol-head">
        <h1>{{ setting('site.name', config('app.name')) }}</h1>
        <p>{{ setting('site.intro', setting('site.tagline', '')) }}</p>
    </div>

    <form class="ol-controls" method="get" action="{{ route('search') }}" role="search">
        <div class="ol-search">
            <label class="sr-only" for="ol-q">Search products</label>
            <input id="ol-q" type="search" name="q"
                   placeholder="{{ setting('site.search_placeholder', 'Search by name or product code…') }}"
                   autocomplete="off">
        </div>

        <label class="sr-only" for="ol-category">Category</label>
        <select id="ol-category" class="ol-select" onchange="window.location.href = this.value">
            <option value="{{ route('shop.index') }}">All categories</option>
            @foreach($categories as $category)
                <option value="{{ route('shop.category', $category->slug) }}">{{ $category->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="ol-add">Search</button>
        <span class="ol-count">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</span>
    </form>

    <x-order-list :products="$products" :show-stock="(bool) setting('site.show_stock', true)" />

    <div class="ol-note">{{ $products->links() }}</div>
</div>
@endsection
