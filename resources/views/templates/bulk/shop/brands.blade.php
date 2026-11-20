@extends('layouts.app')

{{-- /brands: every brand with something on sale, as plain cards. Each links to its page (/brands/{slug}). --}}

@section('content')
<div class="bk-wrap mt-6">
    <x-page-head title="Brands" text="Every brand we supply, in one place." :crumbs="['Brands' => null]" />

    @if($brands->isEmpty())
        <div class="bk-card mt-5 p-10 text-center">
            <p class="font-extrabold">No brands yet</p>
            <a href="{{ route('shop.index') }}" class="bk-btn bk-btn-outline mt-4">Browse all products</a>
        </div>
    @else
        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($brands as $b)
                <a href="{{ $b->url }}" class="bk-card bk-tile-link">
                    @if($b->logo_url)<span class="bk-art !rounded-b-none"><img src="{{ $b->logo_url }}" alt="{{ $b->name }}" loading="lazy" class="!object-contain p-4"></span>@endif
                    <span class="block p-4">
                        <span class="block text-lg font-extrabold">{{ $b->name }}</span>
                        @if($b->description)<span class="bk-muted mt-1 line-clamp-2 block text-sm">{{ $b->description }}</span>@endif
                        <span class="bk-link mt-3 block text-sm">{{ $b->products_count }} {{ \Illuminate\Support\Str::plural('product', $b->products_count) }} →</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
