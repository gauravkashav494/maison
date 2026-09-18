@extends('layouts.app')

@section('content')
<x-page-head title="Combos & curated picks" text="Hand-picked bundles for breakfast, monthly stock-ups, movie nights and healthy weeks." :breadcrumbs="['Combos' => null]" />

<section class="g-container py-6">
    @if($collections->isEmpty())
        <x-empty-state icon="package" title="No combos yet" text="Check back soon — new bundles are added every week." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($collections as $c)
                <a href="{{ $c->url }}" class="card card-hover group overflow-hidden">
                    <div class="relative aspect-[16/10] overflow-hidden bg-leaf-light">
                        @if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">@endif
                        <span class="badge badge-soft absolute left-3 top-3 bg-white">{{ $c->products_count }} {{ Str::plural('item', $c->products_count) }}</span>
                        @if($c->is_featured)<span class="badge badge-off absolute right-3 top-3">Popular</span>@endif
                    </div>
                    <div class="p-4">
                        <h2 class="text-lg font-extrabold group-hover:text-leaf">{{ $c->name }}</h2>
                        <p class="mt-1 line-clamp-2 text-sm text-slate">{{ $c->description }}</p>
                        <span class="section-link mt-3">Shop this combo <x-ico name="arrow-right" :size="14" /></span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection
