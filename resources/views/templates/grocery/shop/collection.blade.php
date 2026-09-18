@extends('layouts.app')

@section('content')
@php $total = $products->sum('price'); $mrp = $products->sum(fn ($p) => $p->compare_at_price ?: $p->price); @endphp
<div class="g-container hidden pt-4 lg:block"><x-breadcrumbs :items="['Combos' => route('collections.index'), $collection->name => null]" /></div>

<section class="g-container py-4 lg:py-6">
    <div class="relative overflow-hidden rounded-2xl bg-leaf text-white">
        @if($collection->hero_image_url ?? $collection->image_url)<img src="{{ $collection->hero_image_url ?? $collection->image_url }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-50">@endif
        <div class="absolute inset-0 bg-gradient-to-r from-ink/80 via-ink/40 to-transparent"></div>
        <div class="relative p-6 sm:p-10 lg:max-w-2xl lg:p-12">
            @if($collection->season)<p class="text-xs font-bold uppercase tracking-wider text-white/80">{{ $collection->season }}</p>@endif
            <h1 class="mt-1 text-2xl font-extrabold leading-tight sm:text-4xl">{{ $collection->name }}</h1>
            @if($collection->description)<p class="mt-2 text-sm text-white/85 sm:text-base">{{ $collection->description }}</p>@endif
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                <span class="rounded-full bg-white/15 px-3 py-1 font-semibold">{{ $products->count() }} items</span>
                <span class="rounded-full bg-white/15 px-3 py-1 font-semibold">Combo value {{ money($total) }}@if($mrp > $total) <span class="text-white/70 strike">{{ money($mrp) }}</span>@endif</span>
            </div>
        </div>
    </div>

    @if($collection->body)<div class="prose-g mx-auto mt-8 max-w-3xl text-sm">{!! $collection->body !!}</div>@endif

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        @foreach($products as $product)<x-product-card :product="$product" />@endforeach
    </div>

    @if($related->isNotEmpty())
        <div class="mt-10">
            <x-section-head title="More combos" :href="route('collections.index')" />
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach($related as $c)
                    <a href="{{ $c->url }}" class="card card-hover flex items-center gap-3 p-3">
                        <span class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-leaf-light">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span>
                        <span><span class="block text-sm font-bold">{{ $c->name }}</span><span class="line-clamp-2 text-xs text-slate">{{ $c->description }}</span></span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endsection
