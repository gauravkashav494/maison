@extends('layouts.app', ['appBar' => ['title' => 'Plumber in '.$area->name, 'back' => true], 'stickyCta' => true])

@section('content')
@php $biz = template()->contact(); @endphp
<section class="relative overflow-hidden bg-deep text-white">
    @if($area->image_url)<img src="{{ $area->image_url }}" alt="" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover opacity-30">@endif
    <div class="absolute inset-0 bg-gradient-to-r from-deep via-deep/80 to-transparent"></div>
    <div class="ps-container relative py-8 lg:py-16">
        <x-breadcrumbs :items="['Service areas' => route('areas.index'), $area->name => null]" class="mb-3 text-white/70 [&_a]:text-white/80 [&_span]:text-white" />
        <p class="eyebrow text-accent"><x-ico name="map-pin" :size="13" /> {{ $area->state }}</p>
        <h1 class="mt-2 font-display text-3xl font-extrabold leading-tight lg:text-5xl">Plumbing services in {{ $area->name }}</h1>
        <p class="mt-3 max-w-2xl text-sm text-white/85 lg:text-lg">{{ $area->excerpt }}</p>
        <div class="mt-5 flex flex-wrap items-center gap-2.5">
            <a href="{{ route('booking.create', ['area' => $area->name]) }}" class="btn btn-accent btn-lg">Book a plumber in {{ $area->name }}</a>
            <a href="{{ $biz['phone_href'] }}" class="btn btn-glass btn-lg"><x-ico name="phone" :size="18" /> Call now</a>
            @if($area->response_time)<span class="pill bg-white/15 text-white"><x-ico name="bolt" :size="12" class="text-accent" /> Emergency response {{ $area->response_time }}</span>@endif
        </div>
    </div>
</section>

<div class="ps-container grid gap-8 py-6 lg:grid-cols-12 lg:gap-10 lg:py-12">
    <div class="space-y-8 lg:col-span-8">
        @if($area->description)<section class="prose-p">{!! $area->description !!}</section>@endif
        @if(!empty($area->localities))
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">Localities we cover in {{ $area->name }}</h2>
                <div class="flex flex-wrap gap-2">@foreach($area->localities as $l)<span class="chip"><x-ico name="map-pin" :size="13" class="text-primary" /> {{ $l }}</span>@endforeach</div>
            </section>
        @endif
        <section>
            <h2 class="sec-title mb-3 text-lg lg:text-2xl">Services available in {{ $area->name }}</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:gap-4">@foreach($services as $s)<x-service-card :service="$s" />@endforeach</div>
        </section>
        @if($testimonials->isNotEmpty())
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">Customers in {{ $area->name }} say</h2>
                <div class="rail rail-bleed no-scrollbar lg:grid lg:grid-cols-2 lg:gap-4 lg:overflow-visible lg:p-0 lg:mx-0">@foreach($testimonials as $t)<x-review-card :review="$t" compact class="lg:w-auto" />@endforeach</div>
            </section>
        @endif
    </div>
    <aside class="lg:col-span-4">
        <div class="sticky top-28 space-y-4">
            <div class="card p-5">
                <p class="font-display text-base font-extrabold">Local team, {{ $area->name }}</p>
                <ul class="mt-3 space-y-2 text-sm text-slate">
                    <li class="flex items-center gap-2"><x-ico name="clock" :size="16" class="text-primary" /> {{ $biz['hours'] }}</li>
                    @if($biz['emergency_available'])<li class="flex items-center gap-2"><x-ico name="alert" :size="16" class="text-danger" /> Emergencies 24×7</li>@endif
                    <li class="flex items-center gap-2"><x-ico name="phone" :size="16" class="text-primary" /> <a href="{{ $biz['phone_href'] }}" class="font-semibold text-ink">{{ $biz['phone'] }}</a></li>
                </ul>
                <a href="{{ route('booking.create', ['area' => $area->name]) }}" class="btn btn-primary btn-block mt-4">Book a plumber</a>
            </div>
            @if($others->isNotEmpty())
                <div class="card p-5">
                    <p class="font-display text-sm font-extrabold">Other areas</p>
                    <ul class="mt-2 flex flex-wrap gap-2">@foreach($others as $o)<li><a href="{{ $o->url }}" class="chip">{{ $o->name }}</a></li>@endforeach</ul>
                </div>
            @endif
        </div>
    </aside>
</div>
<x-sticky-actions :book-url="route('booking.create', ['area' => $area->name])" />
@endsection