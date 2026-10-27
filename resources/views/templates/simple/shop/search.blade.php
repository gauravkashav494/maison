@extends('layouts.app')

{{-- Search results, in the same table as the full list. --}}

@section('content')
<div class="ol-page">
    <div class="ol-head">
        <h1>{{ $q ? 'Results for “'.$q.'”' : 'Search' }}</h1>
        <p>{{ setting('site.intro', '') }}</p>
    </div>

    <form class="ol-controls" method="get" action="{{ route('search') }}" role="search">
        <div class="ol-search">
            <label class="sr-only" for="ol-q">Search products</label>
            <input id="ol-q" type="search" name="q" value="{{ $q }}"
                   placeholder="{{ setting('site.search_placeholder', 'Search by name or product code…') }}"
                   autocomplete="off" autofocus>
        </div>
        <button type="submit" class="ol-add">Search</button>
        <a href="{{ route('shop.index') }}" class="ol-choose">Back to the full list</a>
        @if($q)
            <span class="ol-count">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('match', $products->total()) }}</span>
        @endif
    </form>

    @if($q)
        <x-order-list
            :products="$products"
            :show-stock="(bool) setting('site.show_stock', true)"
            :empty="setting('site.empty_text', 'Nothing matches that search.')" />
        <div class="ol-note">{{ $products->links() }}</div>
    @else
        <p class="ol-empty">Type a product name or code to search the list.</p>
    @endif
</div>
@endsection
