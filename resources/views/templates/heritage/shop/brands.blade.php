@extends('layouts.app')

{{-- /brands: every brand with something on sale. Each links to its page (/brands/{slug}). --}}

@section('content')
<x-page-head title="Shop by brand" text="The houses and makers we stock." :breadcrumbs="['Brands' => null]" />

<section class="h-container py-6">
    @if($brands->isEmpty())
        <x-empty-state icon="package" title="No brands yet" text="Browse the shop to see everything we stock." />
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($brands as $b)
                <a href="{{ $b->url }}" class="card card-hover group flex flex-col overflow-hidden">
                    <div class="grid aspect-[16/10] place-items-center bg-cream p-5">
                        @if($b->logo_url)
                            <img src="{{ $b->logo_url }}" alt="{{ $b->name }}" loading="lazy" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="grid h-14 w-14 place-items-center rounded-full bg-white text-2xl font-extrabold">{{ Str::upper(Str::substr($b->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-4">
                        <h2 class="text-lg font-semibold group-hover:text-red">{{ $b->name }}</h2>
                        @if($b->description)<p class="mt-1 line-clamp-2 text-sm text-muted">{{ $b->description }}</p>@endif
                        <span class="section-link mt-auto pt-3">{{ $b->products_count }} {{ Str::plural('product', $b->products_count) }} <x-ico name="arrow-right" :size="14" /></span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection
