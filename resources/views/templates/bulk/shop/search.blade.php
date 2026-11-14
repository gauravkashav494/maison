@extends('layouts.app')

{{-- Search results as the same order list as the shop — one row per size, each with its own
     code, price, stock and quantity box; the search box itself is in the header. --}}

@section('content')
<div class="bk-wrap mt-6">
    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
        {{ $q ? 'Results for “'.$q.'”' : 'Search the catalogue' }}
    </h1>
    @if($q)
        <p class="bk-muted mt-1 text-sm">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }} found</p>
    @endif

    @if(! $q)
        <p class="bk-muted mt-2">Type a product name, brand or code into the search box above.</p>
    @elseif($products->isEmpty())
        <div class="bk-card mt-5 p-10 text-center">
            <x-heroicon-o-magnifying-glass class="mx-auto size-10 text-brand" aria-hidden="true" />
            <p class="mt-3 font-bold">{{ tsetting('site.empty_text', 'No item matches that.') }}</p>
            <p class="mt-4 flex flex-wrap justify-center gap-3">
                <a href="{{ route('shop.index') }}" class="bk-btn bk-btn-outline">Browse all products</a>
                <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary">Bulk order</a>
            </p>
        </div>
    @else
        <div class="mt-5">
            <x-product-list :products="$products" :show-stock="(bool) tsetting('site.show_stock', true)" />
        </div>
        <div class="bk-pager mt-8">{{ $products->links() }}</div>
    @endif
</div>
@endsection
