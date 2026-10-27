@extends('layouts.app')

{{--
    The order list. The shared ShopController provides the paginated products, the categories
    and the current filters, so this view only has to present them: a search box, a category
    picker and the table.
--}}

@section('content')
<div class="ol-page">
    <div class="ol-head">
        <h1>{{ $title ?? setting('site.name', 'Order list') }}</h1>
        <p>{{ setting('site.intro', '') }}</p>
    </div>

    <form class="ol-controls" method="get" action="{{ route('search') }}" role="search">
        <div class="ol-search">
            <label class="sr-only" for="ol-q">Search products</label>
            <input id="ol-q" type="search" name="q" value="{{ request('q') }}"
                   placeholder="{{ setting('site.search_placeholder', 'Search by name or product code…') }}"
                   autocomplete="off">
        </div>

        <label class="sr-only" for="ol-category">Category</label>
        <select id="ol-category" class="ol-select" onchange="window.location.href = this.value">
            <option value="{{ route('shop.index') }}">All categories</option>
            @foreach($categories ?? [] as $category)
                <option value="{{ route('shop.category', $category->slug) }}" @selected(($category ?? null) && request()->is('shop/'.$category->slug))>{{ $category->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="ol-add">Search</button>

        @if(request('q'))
            <a href="{{ route('shop.index') }}" class="ol-choose">Clear search</a>
        @endif

        <span class="ol-count">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</span>
    </form>

    <x-order-list :products="$products" :show-stock="(bool) setting('site.show_stock', true)" />

    <div class="ol-note">{{ $products->links() }}</div>
</div>
@endsection
