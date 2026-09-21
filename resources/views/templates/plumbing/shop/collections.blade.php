@extends('layouts.app')

@section('content')
<x-page-head title="Project kits" text="Everything for a job in one list — bathroom starter kits, CPVC hot-water lines, overhead tank setups and the plumber’s toolkit." :breadcrumbs="['Project kits' => null]" />

<section class="p-container py-6">
    @if($collections->isEmpty())
        <x-empty-state icon="package" title="No kits yet" text="Check back soon — new project kits are added regularly." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($collections as $c)
                <a href="{{ $c->url }}" class="card card-hover group overflow-hidden">
                    <div class="relative aspect-[16/10] overflow-hidden bg-sky">
                        @if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                        @else{{-- No cover photo: tile the first products in the kit --}}<div class="grid h-full grid-cols-2 grid-rows-2 gap-0.5">@foreach($c->products()->active()->take(4)->get() as $kp)<div class="overflow-hidden bg-white">@if($kp->image_urls)<img src="{{ $kp->image_urls[0] }}" alt="" class="img-cover" loading="lazy">@endif</div>@endforeach</div>@endif
                        <span class="badge badge-out absolute left-3 top-3 bg-white">{{ $c->products_count }} {{ Str::plural('product', $c->products_count) }}</span>
                        @if($c->is_featured)<span class="badge badge-off absolute right-3 top-3">Popular</span>@endif
                    </div>
                    <div class="p-4">
                        <h2 class="font-display text-lg font-bold group-hover:text-primary">{{ $c->name }}</h2>
                        <p class="mt-1 line-clamp-2 text-sm text-slate">{{ $c->description }}</p>
                        <span class="sec-link mt-3">Shop this kit <x-ico name="arrow-right" :size="14" /></span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection
