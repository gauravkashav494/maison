@extends('layouts.app')

{{-- Collections (curated product groups) as plain cards. Replaces the shared editorial grid. --}}

@section('content')
<div class="bk-wrap mt-6">
    <x-page-head title="Collections" text="Products grouped for a job or a kind of business." :crumbs="['Collections' => null]" />

    @if($collections->isEmpty())
        <div class="bk-card mt-5 p-10 text-center">
            <p class="font-extrabold">No collections yet</p>
            <a href="{{ route('shop.index') }}" class="bk-btn bk-btn-outline mt-4">Browse all products</a>
        </div>
    @else
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($collections as $c)
                <a href="{{ $c->url }}" class="bk-card bk-tile-link">
                    @if($c->image_url)<span class="bk-art !rounded-b-none"><img src="{{ $c->image_url }}" alt="" loading="lazy"></span>@endif
                    <span class="block p-4">
                        <span class="block text-lg font-extrabold">{{ $c->name }}</span>
                        @if($c->description)<span class="bk-muted mt-1 block text-sm">{{ $c->description }}</span>@endif
                        <span class="bk-link mt-3 block text-sm">{{ $c->products_count }} {{ \Illuminate\Support\Str::plural('product', $c->products_count) }} →</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
