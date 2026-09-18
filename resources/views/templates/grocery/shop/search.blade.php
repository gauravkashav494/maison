@extends('layouts.app')

@section('content')
@php $gs = tsetting('site'); @endphp
<x-page-head :title="$q ? 'Results for “'.$q.'”' : 'Search'" :text="$q ? $products->total().' '.Str::plural('product', $products->total()).' found' : 'Type what you need — atta, milk, snacks…'" :breadcrumbs="['Search' => null]" />

<section class="g-container py-6">
    <form action="{{ route('search') }}" method="get" role="search" class="relative mx-auto max-w-2xl">
        <x-ico name="search" :size="18" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate" />
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ $gs['search_placeholder'] ?? 'Search products…' }}" class="field h-12 pl-11 pr-28" autofocus>
        <button type="submit" class="btn btn-primary absolute right-1.5 top-1/2 -translate-y-1/2">Search</button>
    </form>

    @if($q === '')
        <div class="mx-auto mt-6 max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-wider text-mist">Popular searches</p>
            <div class="mt-2 flex flex-wrap gap-2">@foreach($gs['search_suggestions'] ?? [] as $t)<a href="{{ route('search', ['q' => $t]) }}" class="chip">{{ $t }}</a>@endforeach</div>
        </div>
    @elseif($products->isEmpty())
        <x-empty-state class="mx-auto mt-8 max-w-2xl" icon="search" title="No results for “{{ $q }}”" text="Check the spelling or try a broader term like “dal” or “biscuits”.">
            <a href="{{ route('shop.index') }}" class="btn btn-outline">Browse all products</a>
        </x-empty-state>
    @else
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach($products as $product)<x-product-card :product="$product" :priority="$loop->index < 4" />@endforeach
        </div>
        @if($products->hasPages())<div class="mt-8">{{ $products->links('vendor.pagination.grocery') }}</div>@endif
    @endif
</section>
@endsection
